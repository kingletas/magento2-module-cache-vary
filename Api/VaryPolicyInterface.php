<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Api;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;

/**
 * Every rule in force, applied together.
 */
interface VaryPolicyInterface
{
    public function apply(ContextSnapshot $snapshot, ?int $storeId = null): ContextSnapshot;

    /**
     * @return VaryRuleInterface[]
     */
    public function rules(): array;

    /**
     * The most cache variants the governed keys may produce between them.
     */
    public function ceiling(?int $storeId = null): ?int;
}
