<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Segment;

use Commerce\CacheVary\Model\Segment\SegmentConditionReader;
use PHPUnit\Framework\TestCase;

/**
 * The shapes are Adobe Commerce 2.4 serialization, not invented.
 */
final class SegmentConditionReaderTest extends TestCase
{
    private const SEGMENT_CONDITION = 'Magento\\\\CustomerSegment\\\\Model\\\\Segment\\\\Condition\\\\Segment';

    public function testARuleWithNoSegmentConditionReadsAsEmpty(): void
    {
        $json = '{"type":"Magento\\\\SalesRule\\\\Model\\\\Rule\\\\Condition\\\\Combine","conditions":[]}';

        self::assertFalse($this->reader()->mentionsSegments($json));
        self::assertSame([], $this->reader()->read($json));
    }

    /**
     * The shape a segment-scoped cart rule carries.
     */
    public function testItReadsASegmentNestedInsideACombine(): void
    {
        $json = '{"type":"Magento\\\\SalesRule\\\\Model\\\\Rule\\\\Condition\\\\Combine","attribute":null,'
            . '"operator":null,"value":"1","aggregator":"all","conditions":['
            . '{"type":"' . self::SEGMENT_CONDITION . '","attribute":false,"operator":"==","value":"3"},'
            . '{"type":"Magento\\\\SalesRule\\\\Model\\\\Rule\\\\Condition\\\\Address","attribute":"base_subtotal",'
            . '"operator":">=","value":"100"}]}';

        self::assertTrue($this->reader()->mentionsSegments($json));
        self::assertSame([3], $this->reader()->read($json));
    }

    public function testItReadsAMultiValuedSegmentCondition(): void
    {
        $json = '{"type":"Combine","conditions":[{"type":"' . self::SEGMENT_CONDITION
            . '","operator":"()","value":["3","7","12"]}]}';

        self::assertSame([3, 7, 12], $this->reader()->read($json));
    }

    /**
     * Conditions nest arbitrarily, so a shallow read would miss this one.
     */
    public function testItReadsASegmentBuriedTwoCombinesDeep(): void
    {
        $json = '{"type":"Combine","conditions":[{"type":"Combine","conditions":['
            . '{"type":"Combine","conditions":[{"type":"' . self::SEGMENT_CONDITION . '","value":"9"}]}]}]}';

        self::assertSame([9], $this->reader()->read($json));
    }

    public function testDuplicatesCollapse(): void
    {
        $json = '{"type":"Combine","conditions":['
            . '{"type":"' . self::SEGMENT_CONDITION . '","value":"4"},'
            . '{"type":"' . self::SEGMENT_CONDITION . '","value":"4"}]}';

        self::assertSame([4], $this->reader()->read($json));
    }

    /**
     * A rule Magento serialised with the pre-2.3 PHP serializer still mentions the class.
     */
    public function testAnUndecodableRuleReadsAsUnknownRatherThanEmpty(): void
    {
        $php = 'a:2:{s:4:"type";s:52:"Magento\\CustomerSegment\\Model\\Segment\\Condition\\Segment";}';

        self::assertTrue($this->reader()->mentionsSegments($php));
        self::assertNull($this->reader()->read($php));
    }

    /**
     * Mentioning the class but carrying no id is not the same as carrying none.
     */
    public function testASegmentConditionWithNoValueReadsAsUnknown(): void
    {
        $json = '{"type":"Combine","conditions":[{"type":"' . self::SEGMENT_CONDITION . '","value":""}]}';

        self::assertNull($this->reader()->read($json));
    }

    public function testEmptyInputIsNotASegmentRule(): void
    {
        self::assertFalse($this->reader()->mentionsSegments(''));
        self::assertSame([], $this->reader()->read(''));
    }

    private function reader(): SegmentConditionReader
    {
        return new SegmentConditionReader();
    }
}
