<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Fake;

use Commerce\CacheVary\Api\PolicyGuardInterface;
use Commerce\CacheVary\Model\Vary\GuardDecision;
use Commerce\CacheVary\Model\Vary\GuardOutcome;

/**
 * A guard that answers however the test needs.
 */
class StubPolicyGuard implements PolicyGuardInterface
{
    public function __construct(
        private readonly bool $applies,
        private readonly string $reason = 'stubbed',
        private readonly ?GuardOutcome $outcome = null
    ) {
    }

    public function decide(?int $storeId = null): GuardDecision
    {
        return new GuardDecision(
            $this->outcome ?? ($this->applies ? GuardOutcome::Applies : GuardOutcome::NotApplied),
            $this->reason
        );
    }
}
