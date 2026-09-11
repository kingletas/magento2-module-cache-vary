<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Unit\Model\Vary;

use Kingletas\CacheVary\Model\Vary\ContextSnapshot;
use PHPUnit\Framework\TestCase;

class ContextSnapshotTest extends TestCase
{
    public function testWithoutDropsTheValueAndItsDefault(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1, 'b' => 2], ['a' => 0, 'b' => 0]);

        $result = $snapshot->without('a');

        $this->assertSame(['b' => 2], $result->data());
        $this->assertSame(['b' => 0], $result->defaults());
    }

    /**
     * An orphaned value would make `Http\Context::getData()` read a default that is not there.
     */
    public function testWithAlwaysLeavesADefaultToCompareAgainst(): void
    {
        $result = (new ContextSnapshot())->with('a', ['1']);

        $this->assertSame(['a' => ['1']], $result->data());
        $this->assertArrayHasKey('a', $result->defaults());
    }

    public function testWithKeepsAnExistingDefault(): void
    {
        $snapshot = new ContextSnapshot(['a' => ['1', '2']], ['a' => []]);

        $this->assertSame(['a' => []], $snapshot->with('a', ['1'])->defaults());
    }

    public function testTheOriginalIsNeverChanged(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1], ['a' => 0]);

        $snapshot->without('a');
        $snapshot->with('a', 9);

        $this->assertSame(['a' => 1], $snapshot->data());
    }

    public function testEqualityComparesBothHalves(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1], ['a' => 0]);

        $this->assertTrue($snapshot->equals(new ContextSnapshot(['a' => 1], ['a' => 0])));
        $this->assertFalse($snapshot->equals(new ContextSnapshot(['a' => 1], ['a' => 1])));
        $this->assertFalse($snapshot->equals(new ContextSnapshot(['a' => 2], ['a' => 0])));
    }

    public function testMissingKeysReadAsNullRatherThanWarning(): void
    {
        $snapshot = new ContextSnapshot();

        $this->assertFalse($snapshot->has('a'));
        $this->assertNull($snapshot->valueOf('a'));
    }
}
