<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Model;

use Kingletas\Foundation\Model\Config\ModuleConfig;

/**
 * Typed access to this module's settings.
 */
class Config extends ModuleConfig
{
    /**
     * Whether the declared rules are applied to the cache key at all.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('policy/enabled', $storeId);
    }

    /**
     * The most cache variants the governed keys may produce before the report fails.
     */
    public function getBucketBudget(?int $storeId = null): int
    {
        return $this->getPositiveInt('policy/bucket_budget', 8, $storeId);
    }
}
