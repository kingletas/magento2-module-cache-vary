<?php
/**
 * GuardDecision.php
 *
 * @package     Commerce_CacheVary
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Model\Vary;

/**
 * Whether the policy may touch this store's cache key, and why.
 */
class GuardDecision
{
    public function __construct(
        private readonly GuardOutcome $outcome,
        private readonly string $reason
    ) {
    }

    public function outcome(): GuardOutcome
    {
        return $this->outcome;
    }

    public function applies(): bool
    {
        return $this->outcome === GuardOutcome::Applies;
    }

    public function isMisconfigured(): bool
    {
        return $this->outcome === GuardOutcome::Misconfigured;
    }

    /**
     * A sentence an operator can act on, whichever way the decision went.
     */
    public function reason(): string
    {
        return $this->reason;
    }
}
