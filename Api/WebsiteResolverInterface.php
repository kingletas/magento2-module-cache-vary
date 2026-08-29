<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Api;

/**
 * Which website a store belongs to, because segments are scoped by website and settings by store.
 */
interface WebsiteResolverInterface
{
    /**
     * Null for "no particular store", which means every website.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException When the store id does not exist.
     */
    public function websiteIdOf(?int $storeId): ?int;
}
