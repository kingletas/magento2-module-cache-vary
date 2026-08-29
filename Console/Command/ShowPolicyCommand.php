<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Console\Command;

use Commerce\CacheVary\Api\CacheRelevantSegmentsInterface;
use Commerce\CacheVary\Api\PolicyGuardInterface;
use Commerce\CacheVary\Api\WebsiteResolverInterface;
use Commerce\CacheVary\Api\VaryPolicyInterface;
use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Model\Segment\SegmentUsage;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\GuardDecision;
use Commerce\CacheVary\Model\Vary\GuardOutcome;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Answers "how many copies of every page can this store's cache key still produce".
 */
class ShowPolicyCommand extends Command
{
    private const OPTION_STORE = 'store';
    private const OPTION_BUDGET = 'budget';
    private const OPTION_REQUIRE_ENABLED = 'require-enabled';

    public function __construct(
        private readonly VaryPolicyInterface $policy,
        private readonly PolicyGuardInterface $guard,
        private readonly CacheRelevantSegmentsInterface $segments,
        private readonly WebsiteResolverInterface $websites,
        private readonly Config $config,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription('Show what the full-page-cache key is made of, and fail when it can fragment.')
            ->addOption(
                self::OPTION_STORE,
                's',
                InputOption::VALUE_REQUIRED,
                'Store id to read settings for, and whose website scopes the segment check.'
            )
            ->addOption(self::OPTION_BUDGET, 'b', InputOption::VALUE_REQUIRED, 'Override the configured budget.')
            ->addOption(
                self::OPTION_REQUIRE_ENABLED,
                null,
                InputOption::VALUE_NONE,
                'Treat a policy that is not in force as a failure, for use as a CI gate.'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = $this->intOption($input, self::OPTION_STORE);

        try {
            $websiteId = $this->websites->websiteIdOf($storeId);
        } catch (NoSuchEntityException) {
            $output->writeln(sprintf('<error>No store with id %d.</error>', (int) $storeId));

            return Command::INVALID;
        }

        $budget = $this->intOption($input, self::OPTION_BUDGET) ?? $this->config->getBucketBudget($storeId);
        $rules = $this->policy->rules();
        $decision = $this->guard->decide($storeId);

        $this->printRules($output, $storeId, $budget, $decision);

        if ($rules === []) {
            $output->writeln('<comment>No rules are declared. See the rules argument in di.xml.</comment>');

            return $decision->applies() || !$input->getOption(self::OPTION_REQUIRE_ENABLED)
                ? Command::SUCCESS
                : Command::FAILURE;
        }

        if ($decision->isMisconfigured()) {
            $output->writeln(sprintf('<error>Nothing above is being applied: %s.</error>', $decision->reason()));
            $output->writeln('<error>Fix the rules argument in di.xml.</error>');

            return Command::FAILURE;
        }

        if (!$decision->applies()) {
            $output->writeln(sprintf(
                '<comment>Nothing above is being applied: %s.</comment>',
                $decision->reason()
            ));

            return $input->getOption(self::OPTION_REQUIRE_ENABLED) ? Command::FAILURE : Command::SUCCESS;
        }

        $ceiling = $this->reportCeiling($output, $storeId, $budget);
        $coverage = $this->reportCoverage($output, $storeId, $websiteId);

        return $ceiling === Command::SUCCESS ? $coverage : $ceiling;
    }

    /**
     * The other direction: a segment that changes a cached page and is not allowlisted is
     * being served to shoppers it was not built for.
     */
    private function describeOutcome(GuardDecision $decision): string
    {
        return match ($decision->outcome()) {
            GuardOutcome::Applies => 'in force',
            GuardOutcome::NotApplied => 'not applied',
            GuardOutcome::Misconfigured => 'misconfigured',
        };
    }

    private function reportCoverage(OutputInterface $output, ?int $storeId, ?int $websiteId): int
    {
        if (!$this->segments->isAvailable()) {
            $output->writeln(
                '<comment>Customer segments are not installed here, so nothing was checked against '
                . 'cacheable content.</comment>'
            );

            return Command::SUCCESS;
        }

        $usages = $this->segments->findUsages($websiteId);

        if ($usages === []) {
            $output->writeln(sprintf(
                '<info>No customer segment drives content on a cacheable page%s.</info>',
                $websiteId === null ? '' : sprintf(' on website %d', $websiteId)
            ));

            return Command::SUCCESS;
        }

        $uncovered = array_filter(
            $usages,
            fn (SegmentUsage $usage): bool => !$this->survivesThePolicy($usage, $storeId)
        );

        foreach ($uncovered as $usage) {
            $output->writeln(sprintf('<error>Not allowlisted: %s.</error>', $usage->describe()));
        }

        if ($uncovered !== []) {
            $output->writeln(sprintf(
                '<error>%d segment usage(s) change a cached page and are being collapsed.</error>',
                count($uncovered)
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>All %d segment usage(s) that change a cached page are allowlisted.</info>',
            count($usages)
        ));

        return Command::SUCCESS;
    }

    private function printRules(
        OutputInterface $output,
        ?int $storeId,
        int $budget,
        GuardDecision $decision
    ): void {
        $output->writeln(sprintf(
            'Policy: <info>%s</info>   Budget: <info>%d</info> variant(s)',
            $this->describeOutcome($decision),
            $budget
        ));
        $output->writeln(sprintf('Cache: <info>%s</info>', $decision->reason()));

        $rules = $this->policy->rules();

        if ($rules === []) {
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Context key', 'Rule', 'Variants']);

        foreach ($rules as $rule) {
            $ceiling = $rule->ceiling($storeId);

            $table->addRow([
                $rule->key(),
                $rule->describe($storeId),
                $ceiling === null ? '<error>unbounded</error>' : (string) $ceiling,
            ]);
        }

        $table->render();
    }

    private function reportCeiling(OutputInterface $output, ?int $storeId, int $budget): int
    {
        $ceiling = $this->policy->ceiling($storeId);

        if ($ceiling === null) {
            $output->writeln(sprintf(
                '<error>%s can produce an unbounded cache key.</error>',
                $this->worstKey($storeId)
            ));

            return Command::FAILURE;
        }

        if ($ceiling > $budget) {
            $output->writeln(sprintf(
                '<error>%d cache variants permitted, over a budget of %d — widest key is %s.</error>',
                $ceiling,
                $budget,
                $this->worstKey($storeId)
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>%d cache variant(s) permitted, within a budget of %d.</info>',
            $ceiling,
            $budget
        ));

        return Command::SUCCESS;
    }

    /**
     * The key that contributes the most variants, which is the one worth acting on.
     */
    private function worstKey(?int $storeId): string
    {
        $worst = '';
        $widest = -1;

        foreach ($this->policy->rules() as $rule) {
            $ceiling = $rule->ceiling($storeId);

            if ($ceiling === null) {
                return $rule->key();
            }

            if ($ceiling > $widest) {
                $widest = $ceiling;
                $worst = $rule->key();
            }
        }

        return $worst;
    }

    /**
     * Asks the rules themselves whether this segment still reaches the cache key.
     */
    private function survivesThePolicy(SegmentUsage $usage, ?int $storeId): bool
    {
        if (!$usage->isReadable()) {
            return false;
        }

        $key = $this->segments->contextKey();
        $probe = new ContextSnapshot([$key => [(string) $usage->segmentId()]], [$key => []]);

        foreach ($this->policy->rules() as $rule) {
            if ($rule->key() === $key && $rule->apply($probe, $storeId)->has($key)) {
                return true;
            }
        }

        return false;
    }

    private function intOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
