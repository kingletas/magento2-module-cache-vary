<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Unit\Model\Segment;

use Kingletas\CacheVary\Model\Segment\SchemaSegmentSource;
use Kingletas\CacheVary\Model\Segment\SegmentConditionReader;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class SchemaSegmentSourceTest extends TestCase
{
    private const SEGMENT = 'magento_customersegment_segment';
    private const BANNER_LINK = 'magento_banner_customersegment';
    private const BANNER = 'magento_banner';
    private const TARGET_LINK = 'magento_targetrule_customersegment';
    private const TARGET = 'magento_targetrule';
    private const CATALOG_RULE = 'catalogrule';

    private const KINGLETAS_TABLES = [
        self::SEGMENT,
        self::BANNER_LINK,
        self::BANNER,
        self::TARGET_LINK,
        self::TARGET,
        self::CATALOG_RULE,
    ];

    /**
     * The Open Source case: the tables are simply not there.
     */
    public function testItIsUnavailableWithoutTheSegmentTable(): void
    {
        $source = $this->source([self::CATALOG_RULE], []);

        $this->assertFalse($source->isAvailable());
        $this->assertSame([], $source->findUsages());
    }

    public function testItIsAvailableOnceTheSegmentTableExists(): void
    {
        $this->assertTrue($this->source(self::KINGLETAS_TABLES, [[], [], []])->isAvailable());
    }

    public function testItNamesTheDynamicBlockASegmentDrives(): void
    {
        $source = $this->source(self::KINGLETAS_TABLES, [
            [['segment_id' => '7', 'segment_name' => 'Trade', 'owner_name' => 'Trade Pricing Notice']],
            [],
            [],
        ]);

        $usages = $source->findUsages();

        $this->assertCount(1, $usages);
        $this->assertSame('segment 7 "Trade" drives dynamic block "Trade Pricing Notice"', $usages[0]->describe());
    }

    public function testItNamesTheRelatedProductRuleASegmentDrives(): void
    {
        $source = $this->source(self::KINGLETAS_TABLES, [
            [],
            [['segment_id' => '9', 'segment_name' => 'High Value', 'owner_name' => 'High Value Upsells']],
            [],
        ]);

        $this->assertSame(
            'segment 9 "High Value" drives related-product rule "High Value Upsells"',
            $source->findUsages()[0]->describe()
        );
    }

    /**
     * A catalog rule carries its segment inside serialized conditions, so the id needs a name lookup.
     */
    public function testItReadsASegmentOutOfACatalogPriceRule(): void
    {
        $source = $this->source(self::KINGLETAS_TABLES, [
            [],
            [],
            [['rule_id' => '4', 'name' => 'Wholesale Pricing', 'conditions_serialized' => $this->conditions('3')]],
            [['segment_id' => '3', 'name' => 'Wholesale']],
        ]);

        $this->assertSame(
            'segment 3 "Wholesale" drives catalog price rule "Wholesale Pricing"',
            $source->findUsages()[0]->describe()
        );
    }

    /**
     * The dangerous case: a rule that is segment-scoped but cannot be decoded still gets reported.
     */
    public function testAnUndecodableCatalogRuleIsReportedRatherThanSkipped(): void
    {
        $source = $this->source(self::KINGLETAS_TABLES, [
            [],
            [],
            [['rule_id' => '5', 'name' => 'Legacy', 'conditions_serialized' => 'a:1:{s:4:"type";s:6:"'
                . 'Magento\\CustomerSegment\\Model\\Segment\\Condition\\Segment";}']],
        ]);

        $usages = $source->findUsages();

        $this->assertCount(1, $usages);
        $this->assertFalse($usages[0]->isReadable());
    }

    /**
     * A rule pointing at a segment that is inactive or on another website is not a coverage gap here.
     */
    public function testACatalogRuleNamingAnUnknownSegmentIsSkipped(): void
    {
        $source = $this->source(self::KINGLETAS_TABLES, [
            [],
            [],
            [['rule_id' => '6', 'name' => 'Stale', 'conditions_serialized' => $this->conditions('99')]],
            [['segment_id' => '3', 'name' => 'Wholesale']],
        ]);

        $this->assertSame([], $source->findUsages());
    }

    /**
     * A store with segments but no banner module installed must not fatal on a missing table.
     */
    public function testAMissingOwnerTableIsSkippedRatherThanFatal(): void
    {
        $source = $this->source([self::SEGMENT, self::CATALOG_RULE], [[]]);

        $this->assertSame([], $source->findUsages());
    }

    private function conditions(string $segmentId): string
    {
        return '{"type":"Magento\\\\CatalogRule\\\\Model\\\\Rule\\\\Condition\\\\Combine","conditions":['
            . '{"type":"Magento\\\\CustomerSegment\\\\Model\\\\Segment\\\\Condition\\\\Segment","value":"'
            . $segmentId . '"}]}';
    }

    /**
     * @param string[] $tables Tables that exist.
     * @param array<int, array<int, array<string, string>>> $rows One result set per fetchAll, in order.
     */
    private function source(array $tables, array $rows): SchemaSegmentSource
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('isTableExists')->willReturnCallback(
            static fn (string $table): bool => in_array($table, $tables, true)
        );

        $remaining = $rows;
        $connection->method('fetchAll')->willReturnCallback(
            static function () use (&$remaining): array {
                return array_shift($remaining) ?? [];
            }
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new SchemaSegmentSource($resource, new SegmentConditionReader());
    }
}
