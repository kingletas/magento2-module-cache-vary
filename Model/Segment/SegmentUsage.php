<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Model\Segment;

/**
 * One thing on a cacheable page whose content depends on a customer segment.
 */
class SegmentUsage
{
    /**
     * @param int|null $segmentId Null where a rule uses segments but its ids could not be read.
     */
    public function __construct(
        private readonly ?int $segmentId,
        private readonly string $segmentName,
        private readonly string $consumer
    ) {
    }

    public function segmentId(): ?int
    {
        return $this->segmentId;
    }

    public function isReadable(): bool
    {
        return $this->segmentId !== null;
    }

    public function segmentName(): string
    {
        return $this->segmentName;
    }

    /**
     * What depends on it, named the way an operator would recognise it in the admin.
     */
    public function consumer(): string
    {
        return $this->consumer;
    }

    public function describe(): string
    {
        return $this->segmentId === null
            ? sprintf('%s uses customer segments, and its ids could not be read', $this->consumer)
            : sprintf('segment %d "%s" drives %s', $this->segmentId, $this->segmentName, $this->consumer);
    }
}
