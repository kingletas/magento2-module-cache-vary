<?php
/**
 * VaryRuleInterface.php
 *
 * @package     Commerce_CacheVary
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Api;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;

/**
 * What one context key is allowed to contribute to the full-page-cache key.
 */
interface VaryRuleInterface
{
    /**
     * The context key this rule governs, as `Http\Context` names it.
     */
    public function key(): string;

    /**
     * Return the snapshot with this rule applied to its own key.
     */
    public function apply(ContextSnapshot $snapshot, ?int $storeId = null): ContextSnapshot;

    /**
     * The most distinct values this key may still produce, or null if unbounded.
     */
    public function ceiling(?int $storeId = null): ?int;

    /**
     * One line an operator can read in the policy report.
     */
    public function describe(?int $storeId = null): string;
}
