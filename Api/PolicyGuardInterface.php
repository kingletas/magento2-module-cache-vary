<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Api;

use Commerce\CacheVary\Model\Vary\GuardDecision;

/**
 * Decides whether rewriting the cache key is safe on this store.
 */
interface PolicyGuardInterface
{
    public function decide(?int $storeId = null): GuardDecision;
}
