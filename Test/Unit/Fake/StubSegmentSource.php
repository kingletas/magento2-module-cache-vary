<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Fake;

use Commerce\CacheVary\Api\CacheRelevantSegmentsInterface;
use Commerce\CacheVary\Model\Segment\SegmentUsage;

/**
 * A segment source that reports whatever the test needs.
 */
class StubSegmentSource implements CacheRelevantSegmentsInterface
{
    /**
     * @param SegmentUsage[] $usages
     */
    public function __construct(
        private readonly bool $available = true,
        private readonly array $usages = []
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function contextKey(): string
    {
        return 'customer_segment';
    }

    /**
     * @return SegmentUsage[]
     */
    public function findUsages(?int $websiteId = null): array
    {
        return $this->usages;
    }
}
