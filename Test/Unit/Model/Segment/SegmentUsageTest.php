<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Unit\Model\Segment;

use Kingletas\CacheVary\Model\Segment\SegmentUsage;
use PHPUnit\Framework\TestCase;

class SegmentUsageTest extends TestCase
{
    public function testAReadableUsageNamesTheSegmentAndWhatDependsOnIt(): void
    {
        $usage = new SegmentUsage(7, 'Trade', 'dynamic block "Trade Pricing Notice"');

        $this->assertTrue($usage->isReadable());
        $this->assertSame(7, $usage->segmentId());
        $this->assertSame('segment 7 "Trade" drives dynamic block "Trade Pricing Notice"', $usage->describe());
    }

    /**
     * An unreadable rule must not read as "no segments", which is the safe-looking wrong answer.
     */
    public function testAnUnreadableUsageSaysSoRatherThanNamingASegment(): void
    {
        $usage = new SegmentUsage(null, '', 'catalog price rule "Legacy"');

        $this->assertFalse($usage->isReadable());
        $this->assertNull($usage->segmentId());
        $this->assertSame(
            'catalog price rule "Legacy" uses customer segments, and its ids could not be read',
            $usage->describe()
        );
    }
}
