<?php
/**
 * VaryStringPlugin.php
 *
 * @package     Commerce_CacheVary
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Plugin\Framework\App\Http;

use Commerce\CacheVary\Api\PolicyGuardInterface;
use Commerce\CacheVary\Api\VaryHasherInterface;
use Commerce\CacheVary\Api\VaryPolicyInterface;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Magento\Framework\App\Http\Context;

/**
 * Hashes the cache key from a filtered copy of the context, leaving the context itself intact.
 */
class VaryStringPlugin
{
    public function __construct(
        private readonly VaryPolicyInterface $policy,
        private readonly VaryHasherInterface $hasher,
        private readonly PolicyGuardInterface $guard
    ) {
    }

    /**
     * @param callable(): ?string $proceed
     */
    public function aroundGetVaryString(Context $subject, callable $proceed): ?string
    {
        if (!$this->guard->decide()->applies()) {
            return $proceed();
        }

        $raw = $subject->toArray();
        $snapshot = new ContextSnapshot($raw['data'] ?? [], $raw['default'] ?? []);
        $filtered = $this->policy->apply($snapshot);

        if ($filtered->equals($snapshot)) {
            return $proceed();
        }

        return $this->hasher->hash($filtered);
    }
}
