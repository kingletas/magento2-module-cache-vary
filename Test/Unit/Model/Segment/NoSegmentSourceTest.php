<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Segment;

use Commerce\CacheVary\Model\Segment\NoSegmentSource;
use PHPUnit\Framework\TestCase;

class NoSegmentSourceTest extends TestCase
{
    /**
     * Unavailable rather than empty, so the report says "not checked" instead of "nothing found".
     */
    public function testItReportsItselfUnavailable(): void
    {
        $source = new NoSegmentSource();

        $this->assertFalse($source->isAvailable());
        $this->assertSame([], $source->findUsages());
    }
}
