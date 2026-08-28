<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Console\Command;

use Commerce\CacheVary\Console\Command\ShowPolicyCommand;
use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Model\Vary\Rule\AllowlistedValues;
use Commerce\CacheVary\Model\Vary\VaryPolicy;
use Commerce\CacheVary\Test\Unit\Fake\ArrayScopeConfig;
use Commerce\CacheVary\Model\Segment\SegmentUsage;
use Commerce\CacheVary\Model\Vary\GuardOutcome;
use Commerce\CacheVary\Test\Unit\Fake\StubPolicyGuard;
use Commerce\CacheVary\Test\Unit\Fake\RecordingSegmentSource;
use Commerce\CacheVary\Test\Unit\Fake\StubSegmentSource;
use Commerce\CacheVary\Test\Unit\Fake\StubWebsiteResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ShowPolicyCommandTest extends TestCase
{
    private const SECTION = 'commerce_cachevary';
    private const PATH = 'policy/cacheable_customer_segments';

    public function testAnEmptyAllowlistIsOneVariantAndPasses(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => '']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 cache variant(s) permitted', $tester->getDisplay());
    }

    public function testAnAllowlistWithinBudgetPasses(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => '9,10,12', 'budget' => '8']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('8 cache variant(s) permitted', $tester->getDisplay());
    }

    public function testGoingOverBudgetFailsAndNamesTheKey(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => '9,10,12,13', 'budget' => '8']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('16 cache variants permitted', $tester->getDisplay());
        $this->assertStringContainsString('customer_segment', $tester->getDisplay());
    }

    public function testAnUncountableAllowlistFails(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => implode(',', range(1, 31))]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('unbounded', $tester->getDisplay());
    }

    /**
     * A module nobody has switched on is making no claim, so it is not a failure.
     */
    public function testAPolicyThatIsNotInForceSaysWhyAndPasses(): void
    {
        $tester = $this->tester(['enabled' => '0', 'allowlist' => '']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('not applied', $tester->getDisplay());
        $this->assertStringContainsString('the built-in cache', $tester->getDisplay());
    }

    /**
     * A wiring mistake nobody chose, so it fails without anyone asking for a gate.
     */
    public function testAMisconfiguredPolicyFailsWithoutRequireEnabled(): void
    {
        $tester = $this->tester([
            'enabled' => '1',
            'allowlist' => '',
            'guard' => new StubPolicyGuard(
                false,
                'a rule governs customer_group, and collapsing that would serve one shopper the page '
                . 'built for another',
                GuardOutcome::Misconfigured
            ),
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('misconfigured', $tester->getDisplay());
        $this->assertStringContainsString('customer_group', $tester->getDisplay());
        $this->assertStringContainsString('Fix the rules argument in di.xml', $tester->getDisplay());
    }

    /**
     * The trap this reporting exists for: switched on, and silently doing nothing.
     */
    public function testASwitchedOnPolicyOnTheWrongCacheStillReadsAsNotApplied(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => '', 'applies' => false]);

        $this->assertStringContainsString('not applied', $tester->getDisplay());
        $this->assertStringNotContainsString('cache variant(s) permitted', $tester->getDisplay());
    }

    public function testASwitchedOffPolicyFailsWhenTheGateDemandsOne(): void
    {
        $tester = $this->tester(['enabled' => '0', 'allowlist' => '', 'require-enabled' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testNoRulesDeclaredSaysSoRatherThanClaimingSafety(): void
    {
        $config = new Config(
            new ArrayScopeConfig([self::SECTION . '/policy/enabled' => '1']),
            self::SECTION
        );
        $tester = new CommandTester(new ShowPolicyCommand(
            new VaryPolicy(),
            new StubPolicyGuard(true, 'narrowing the Varnish cache key'),
            new StubSegmentSource(false),
            new StubWebsiteResolver(),
            $config,
            'commerce:cache-vary:policy'
        ));

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No rules are declared', $tester->getDisplay());
    }

    public function testAnOpenSourceStoreSaysNothingWasChecked(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => '']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Customer segments are not installed here', $tester->getDisplay());
    }

    public function testNoSegmentDrivingCachedContentPasses(): void
    {
        $tester = $this->tester([
            'enabled' => '1',
            'allowlist' => '',
            'segments' => new StubSegmentSource(true, []),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No customer segment drives content', $tester->getDisplay());
    }

    /**
     * The failure this check exists for: a banner nobody allowlisted, served to everyone.
     */
    public function testASegmentDrivingCachedContentThatIsNotAllowlistedFails(): void
    {
        $tester = $this->tester([
            'enabled' => '1',
            'allowlist' => '',
            'segments' => new StubSegmentSource(true, [
                new SegmentUsage(7, 'Trade', 'dynamic block "Trade Pricing Notice"'),
            ]),
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Not allowlisted: segment 7 "Trade"', $tester->getDisplay());
    }

    public function testTheSameSegmentOnTheAllowlistPasses(): void
    {
        $tester = $this->tester([
            'enabled' => '1',
            'allowlist' => '7',
            'segments' => new StubSegmentSource(true, [
                new SegmentUsage(7, 'Trade', 'dynamic block "Trade Pricing Notice"'),
            ]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('are allowlisted', $tester->getDisplay());
    }

    /**
     * An unreadable rule cannot be covered by any allowlist, so it always surfaces.
     */
    public function testAnUnreadableRuleFailsWhateverIsAllowlisted(): void
    {
        $tester = $this->tester([
            'enabled' => '1',
            'allowlist' => '7',
            'segments' => new StubSegmentSource(true, [
                new SegmentUsage(null, '', 'catalog price rule "Legacy"'),
            ]),
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('could not be read', $tester->getDisplay());
    }

    /**
     * With the policy not in force nothing is collapsed, so coverage is not a failure.
     */
    public function testCoverageIsNotCheckedWhenThePolicyIsNotApplied(): void
    {
        $tester = $this->tester([
            'enabled' => '0',
            'allowlist' => '',
            'segments' => new StubSegmentSource(true, [
                new SegmentUsage(7, 'Trade', 'dynamic block "Trade Pricing Notice"'),
            ]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('Not allowlisted', $tester->getDisplay());
    }

    /**
     * The segment check is scoped by website, and the website comes from the store asked about.
     */
    public function testTheSegmentCheckIsNarrowedToTheStoresWebsite(): void
    {
        $segments = new RecordingSegmentSource();

        $this->tester(['enabled' => '1', 'allowlist' => '', 'segments' => $segments, 'store' => '2']);

        $this->assertTrue($segments->wasAsked);
        $this->assertSame(2, $segments->askedFor);
    }

    /**
     * With no store named there is no website to narrow to, so every one is checked.
     */
    public function testWithoutAStoreEveryWebsiteIsChecked(): void
    {
        $segments = new RecordingSegmentSource();

        $this->tester(['enabled' => '1', 'allowlist' => '', 'segments' => $segments]);

        $this->assertTrue($segments->wasAsked);
        $this->assertNull($segments->askedFor);
    }

    /**
     * A store id nobody recognises is a mistake worth stopping on, not a silent widening.
     */
    public function testAnUnknownStoreIsRefused(): void
    {
        $tester = $this->tester(['enabled' => '1', 'allowlist' => '', 'store' => '404']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('No store with id 404', $tester->getDisplay());
    }

    /**
     * @param array<string, string|bool> $settings
     */
    private function refusal(array $settings): string
    {
        return ($settings['enabled'] ?? '1') === '1'
            ? 'the full-page cache is the built-in cache, which this policy is not verified against'
            : 'the policy is switched off, and the full-page cache is the built-in cache';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function tester(array $settings): CommandTester
    {
        $values = [
            self::SECTION . '/policy/enabled' => (string) ($settings['enabled'] ?? '1'),
            self::SECTION . '/' . self::PATH => (string) ($settings['allowlist'] ?? ''),
        ];

        if (isset($settings['budget'])) {
            $values[self::SECTION . '/policy/bucket_budget'] = (string) $settings['budget'];
        }

        $config = new Config(new ArrayScopeConfig($values), self::SECTION);
        $policy = new VaryPolicy([new AllowlistedValues($config, 'customer_segment', self::PATH)]);
        $applies = ($settings['applies'] ?? null) === false
            ? false
            : ($settings['enabled'] ?? '1') === '1';
        $guard = $settings['guard'] ?? ($applies
            ? new StubPolicyGuard(true, 'narrowing the Varnish cache key')
            : new StubPolicyGuard(false, $this->refusal($settings)));
        $segments = $settings['segments'] ?? new StubSegmentSource(false);
        $websites = $settings['websites'] ?? new StubWebsiteResolver([1 => 1, 2 => 2]);
        $tester = new CommandTester(
            new ShowPolicyCommand($policy, $guard, $segments, $websites, $config, 'commerce:cache-vary:policy')
        );

        $arguments = isset($settings['require-enabled']) ? ['--require-enabled' => true] : [];

        if (isset($settings['store'])) {
            $arguments['--store'] = $settings['store'];
        }

        $tester->execute($arguments);

        return $tester;
    }
}
