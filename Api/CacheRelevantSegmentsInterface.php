<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Api;

use Commerce\CacheVary\Model\Segment\SegmentUsage;

/**
 * Finds the customer segments that change what a cacheable page renders.
 */
interface CacheRelevantSegmentsInterface
{
    /**
     * False where nothing can answer the question, which is not the same as "nothing uses segments".
     */
    public function isAvailable(): bool;

    /**
     * The `Http\Context` key these segments arrive under, meaningful only while `isAvailable()`.
     */
    public function contextKey(): string;

    /**
     * @param int|null $websiteId Narrow to segments scoped to one website, or all of them.
     * @return SegmentUsage[]
     */
    public function findUsages(?int $websiteId = null): array;
}
