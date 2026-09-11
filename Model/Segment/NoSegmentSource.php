<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Model\Segment;

use Kingletas\CacheVary\Api\CacheRelevantSegmentsInterface;

/**
 * Answers nothing, and says so rather than reporting that nothing uses segments.
 */
class NoSegmentSource implements CacheRelevantSegmentsInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * Never consulted, because nothing asks an unavailable source which key it fills.
     */
    public function contextKey(): string
    {
        return '';
    }

    /**
     * @return SegmentUsage[]
     */
    public function findUsages(?int $websiteId = null): array
    {
        return [];
    }
}
