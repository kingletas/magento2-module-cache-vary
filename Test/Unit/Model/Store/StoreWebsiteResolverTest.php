<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Unit\Model\Store;

use Kingletas\CacheVary\Model\Store\StoreWebsiteResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use PHPUnit\Framework\TestCase;

class StoreWebsiteResolverTest extends TestCase
{
    public function testNoStoreMeansNoWebsiteFilter(): void
    {
        $this->assertNull($this->resolver()->websiteIdOf(null));
    }

    public function testItReadsTheWebsiteOffTheStore(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn('2');

        $this->assertSame(2, $this->resolver($store)->websiteIdOf(3));
    }

    /**
     * An unknown store must not read as "every website".
     */
    public function testAnUnknownStoreIsRefusedRatherThanWidened(): void
    {
        $stores = $this->createMock(StoreRepositoryInterface::class);
        $stores->method('getById')->willThrowException(new NoSuchEntityException(new Phrase('nope')));

        $this->expectException(NoSuchEntityException::class);

        (new StoreWebsiteResolver($stores))->websiteIdOf(99);
    }

    private function resolver(?StoreInterface $store = null): StoreWebsiteResolver
    {
        $stores = $this->createMock(StoreRepositoryInterface::class);
        $stores->method('getById')->willReturn($store ?? $this->createMock(StoreInterface::class));

        return new StoreWebsiteResolver($stores);
    }
}
