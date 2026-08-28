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
 * Remembers which website it was asked about.
 */
class RecordingSegmentSource implements CacheRelevantSegmentsInterface
{
    public bool $wasAsked = false;

    public ?int $askedFor = null;

    /**
     * @param SegmentUsage[] $usages
     */
    public function __construct(private readonly array $usages = [])
    {
    }

    public function isAvailable(): bool
    {
        return true;
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
        $this->wasAsked = true;
        $this->askedFor = $websiteId;

        return $this->usages;
    }
}
