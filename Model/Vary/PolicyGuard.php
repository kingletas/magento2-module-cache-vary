<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Model\Vary;

use Commerce\CacheVary\Api\PolicyGuardInterface;
use Commerce\CacheVary\Api\VaryPolicyInterface;
use Commerce\CacheVary\Model\Config;
use Magento\PageCache\Model\Config as PageCacheConfig;

/**
 * Refuses to rewrite the cache key on any caching application this policy is not verified against.
 */
class PolicyGuard implements PolicyGuardInterface
{
    /**
     * @var array<int, string>
     */
    private const KNOWN_APPLICATIONS = [
        PageCacheConfig::BUILT_IN => 'the built-in cache',
        PageCacheConfig::VARNISH => 'Varnish',
    ];

    /**
     * @param int[] $cachingApplications `system/full_page_cache/caching_application` values to act on.
     * @param string[] $protectedKeys Context keys no rule may govern, whatever `di.xml` says.
     */
    public function __construct(
        private readonly Config $config,
        private readonly PageCacheConfig $pageCacheConfig,
        private readonly VaryPolicyInterface $policy,
        private readonly array $cachingApplications = [],
        private readonly array $protectedKeys = []
    ) {
    }

    public function decide(?int $storeId = null): GuardDecision
    {
        $protected = $this->governedProtectedKeys();

        if ($protected !== []) {
            return new GuardDecision(GuardOutcome::Misconfigured, sprintf(
                'a rule governs %s, and collapsing that would serve one shopper the page built for another',
                implode(' and ', $protected)
            ));
        }

        if (!$this->config->isEnabled($storeId)) {
            return new GuardDecision(GuardOutcome::NotApplied, 'the policy is switched off');
        }

        if (!$this->pageCacheConfig->isEnabled()) {
            return new GuardDecision(
                GuardOutcome::NotApplied,
                'the full-page cache is off, so there is no key to narrow'
            );
        }

        $application = $this->pageCacheConfig->getType();

        if (!in_array($application, $this->accepted(), true)) {
            return new GuardDecision(GuardOutcome::NotApplied, sprintf(
                'the full-page cache is %s, which this policy is not verified against',
                $this->describe($application)
            ));
        }

        return new GuardDecision(
            GuardOutcome::Applies,
            sprintf('narrowing the %s cache key', $this->describe($application))
        );
    }

    /**
     * Keys that say who the shopper is, which a rule may never collapse.
     *
     * @return string[]
     */
    private function governedProtectedKeys(): array
    {
        $governed = [];

        foreach ($this->policy->rules() as $rule) {
            if (in_array($rule->key(), $this->protectedKeys, true)) {
                $governed[] = $rule->key();
            }
        }

        return array_values(array_unique($governed));
    }

    /**
     * @return int[]
     */
    private function accepted(): array
    {
        return array_map('intval', array_values($this->cachingApplications));
    }

    private function describe(int $application): string
    {
        return self::KNOWN_APPLICATIONS[$application] ?? sprintf('caching application %d', $application);
    }
}
