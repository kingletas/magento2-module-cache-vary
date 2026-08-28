<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Fake;

use Commerce\CacheVary\Api\WebsiteResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;

/**
 * Maps store ids to websites from a plain array, and refuses anything not in it.
 */
class StubWebsiteResolver implements WebsiteResolverInterface
{
    /**
     * @param array<int, int> $websiteByStore
     */
    public function __construct(private readonly array $websiteByStore = [])
    {
    }

    public function websiteIdOf(?int $storeId): ?int
    {
        if ($storeId === null) {
            return null;
        }

        if (!array_key_exists($storeId, $this->websiteByStore)) {
            throw new NoSuchEntityException(new Phrase('No such store.'));
        }

        return $this->websiteByStore[$storeId];
    }
}
