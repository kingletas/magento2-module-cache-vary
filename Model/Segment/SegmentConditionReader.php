<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Model\Segment;

/**
 * Pulls the segment ids out of a rule's serialized conditions.
 */
class SegmentConditionReader
{
    /**
     * `Magento\CustomerSegment\Model\Segment\Condition\Segment` with its separators removed,
     * because those are the one part that varies between the serialized forms.
     */
    private const SEGMENT_CONDITION = 'CustomerSegmentModelSegmentConditionSegment';

    /**
     * True where the rule is scoped by segment at all, whether or not the ids can be read.
     */
    public function mentionsSegments(string $serialized): bool
    {
        return str_contains($this->withoutSlashes($serialized), self::SEGMENT_CONDITION);
    }

    /**
     * Drops every separator so the three serialized forms compare as one string.
     */
    private function withoutSlashes(string $value): string
    {
        return str_replace('\\', '', $value);
    }

    /**
     * Null where the rule is segment-scoped but its conditions could not be decoded.
     *
     * @return int[]|null
     */
    public function read(string $serialized): ?array
    {
        if (!$this->mentionsSegments($serialized)) {
            return [];
        }

        $decoded = json_decode($serialized, true);

        if (!is_array($decoded)) {
            return null;
        }

        $ids = [];
        $this->walk($decoded, $ids);

        return $ids === [] ? null : array_values(array_unique($ids));
    }

    /**
     * Conditions nest, so a segment can sit inside any number of combines.
     *
     * @param mixed[] $node
     * @param int[] $ids
     */
    private function walk(array $node, array &$ids): void
    {
        if ($this->mentionsSegments((string) ($node['type'] ?? ''))) {
            foreach ((array) ($node['value'] ?? []) as $value) {
                if (is_scalar($value) && (string) $value !== '') {
                    $ids[] = (int) $value;
                }
            }
        }

        foreach ((array) ($node['conditions'] ?? []) as $child) {
            if (is_array($child)) {
                $this->walk($child, $ids);
            }
        }
    }
}
