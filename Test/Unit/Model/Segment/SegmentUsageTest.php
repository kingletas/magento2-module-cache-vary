<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Segment;

use Commerce\CacheVary\Model\Segment\SegmentUsage;
use PHPUnit\Framework\TestCase;

final class SegmentUsageTest extends TestCase
{
    public function testAReadableUsageNamesTheSegmentAndWhatDependsOnIt(): void
    {
        $usage = new SegmentUsage(7, 'Trade', 'dynamic block "Trade Pricing Notice"');

        self::assertTrue($usage->isReadable());
        self::assertSame(7, $usage->segmentId());
        self::assertSame('segment 7 "Trade" drives dynamic block "Trade Pricing Notice"', $usage->describe());
    }

    /**
     * An unreadable rule must not read as "no segments", which is the safe-looking wrong answer.
     */
    public function testAnUnreadableUsageSaysSoRatherThanNamingASegment(): void
    {
        $usage = new SegmentUsage(null, '', 'catalog price rule "Legacy"');

        self::assertFalse($usage->isReadable());
        self::assertNull($usage->segmentId());
        self::assertSame(
            'catalog price rule "Legacy" uses customer segments, and its ids could not be read',
            $usage->describe()
        );
    }
}
