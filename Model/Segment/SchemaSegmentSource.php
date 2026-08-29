<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Model\Segment;

use Commerce\CacheVary\Api\CacheRelevantSegmentsInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * Reads the segment links straight from the schema, naming no Adobe Commerce class, so an absent
 * table means unavailable rather than empty.
 */
class SchemaSegmentSource implements CacheRelevantSegmentsInterface
{
    private const SEGMENT = 'magento_customersegment_segment';
    private const SEGMENT_WEBSITE = 'magento_customersegment_website';
    private const BANNER_LINK = 'magento_banner_customersegment';
    private const BANNER = 'magento_banner';
    private const TARGET_RULE_LINK = 'magento_targetrule_customersegment';
    private const TARGET_RULE = 'magento_targetrule';
    private const CATALOG_RULE = 'catalogrule';

    /**
     * @param string $contextKey The `Http\Context` key Adobe Commerce files segments under.
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SegmentConditionReader $reader,
        private readonly string $contextKey = 'customer_segment'
    ) {
    }

    public function contextKey(): string
    {
        return $this->contextKey;
    }

    public function isAvailable(): bool
    {
        return $this->exists(self::SEGMENT);
    }

    /**
     * @return SegmentUsage[]
     */
    public function findUsages(?int $websiteId = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return array_merge(
            $this->fromLinkTable(self::BANNER_LINK, self::BANNER, 'banner_id', 'dynamic block', $websiteId),
            $this->fromLinkTable(
                self::TARGET_RULE_LINK,
                self::TARGET_RULE,
                'rule_id',
                'related-product rule',
                $websiteId
            ),
            $this->fromCatalogRules($websiteId)
        );
    }

    /**
     * Banners and target rules both link to segments through a two-column table.
     *
     * @return SegmentUsage[]
     */
    private function fromLinkTable(
        string $linkTable,
        string $ownerTable,
        string $ownerKey,
        string $label,
        ?int $websiteId
    ): array {
        if (!$this->exists($linkTable) || !$this->exists($ownerTable)) {
            return [];
        }

        $select = $this->connection()->select()
            ->from(['link' => $this->table($linkTable)], [])
            ->join(
                ['segment' => $this->table(self::SEGMENT)],
                'segment.segment_id = link.segment_id',
                ['segment_id', 'segment_name' => 'name']
            )
            ->join(
                ['owner' => $this->table($ownerTable)],
                sprintf('owner.%s = link.%s', $ownerKey, $ownerKey),
                ['owner_name' => 'name']
            )
            ->where('segment.is_active = ?', 1);

        $this->restrictToWebsite($select, $websiteId);

        $usages = [];

        foreach ($this->connection()->fetchAll($select) as $row) {
            $usages[] = new SegmentUsage(
                (int) $row['segment_id'],
                (string) $row['segment_name'],
                sprintf('%s "%s"', $label, (string) $row['owner_name'])
            );
        }

        return $usages;
    }

    /**
     * Catalog price rules carry the segment inside their serialized conditions instead.
     *
     * @return SegmentUsage[]
     */
    private function fromCatalogRules(?int $websiteId): array
    {
        if (!$this->exists(self::CATALOG_RULE)) {
            return [];
        }

        $select = $this->connection()->select()
            ->from($this->table(self::CATALOG_RULE), ['rule_id', 'name', 'conditions_serialized'])
            ->where('is_active = ?', 1)
            ->where('conditions_serialized LIKE ?', '%CustomerSegment%');

        $usages = [];
        $names = null;

        foreach ($this->connection()->fetchAll($select) as $row) {
            $label = sprintf('catalog price rule "%s"', (string) $row['name']);
            $ids = $this->reader->read((string) $row['conditions_serialized']);

            if ($ids === null) {
                $usages[] = new SegmentUsage(null, '', $label);

                continue;
            }

            $names ??= $this->segmentNames($websiteId);

            foreach ($ids as $id) {
                if (array_key_exists($id, $names)) {
                    $usages[] = new SegmentUsage($id, $names[$id], $label);
                }
            }
        }

        return $usages;
    }

    /**
     * @return array<int, string>
     */
    private function segmentNames(?int $websiteId): array
    {
        $select = $this->connection()->select()
            ->from(['segment' => $this->table(self::SEGMENT)], ['segment_id', 'name'])
            ->where('segment.is_active = ?', 1);

        $this->restrictToWebsite($select, $websiteId);

        $names = [];

        foreach ($this->connection()->fetchAll($select) as $row) {
            $names[(int) $row['segment_id']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * A segment with no website rows is scoped to none in particular, so it stays in.
     */
    private function restrictToWebsite(Select $select, ?int $websiteId): void
    {
        if ($websiteId === null || !$this->exists(self::SEGMENT_WEBSITE)) {
            return;
        }

        $select->joinLeft(
            ['segment_website' => $this->table(self::SEGMENT_WEBSITE)],
            'segment_website.segment_id = segment.segment_id',
            []
        )->where('segment_website.website_id = ? OR segment_website.segment_id IS NULL', $websiteId);
    }

    private function exists(string $table): bool
    {
        return $this->connection()->isTableExists($this->table($table));
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
