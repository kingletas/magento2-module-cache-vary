<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Model\Store;

use Commerce\CacheVary\Api\WebsiteResolverInterface;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Resolves through the store repository rather than the store manager, which is the whole of what is needed.
 */
class StoreWebsiteResolver implements WebsiteResolverInterface
{
    public function __construct(private readonly StoreRepositoryInterface $stores)
    {
    }

    public function websiteIdOf(?int $storeId): ?int
    {
        return $storeId === null ? null : (int) $this->stores->getById($storeId)->getWebsiteId();
    }
}
