<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use PHPUnit\Framework\TestCase;

final class ContextSnapshotTest extends TestCase
{
    public function testWithoutDropsTheValueAndItsDefault(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1, 'b' => 2], ['a' => 0, 'b' => 0]);

        $result = $snapshot->without('a');

        self::assertSame(['b' => 2], $result->data());
        self::assertSame(['b' => 0], $result->defaults());
    }

    /**
     * An orphaned value would make `Http\Context::getData()` read a default that is not there.
     */
    public function testWithAlwaysLeavesADefaultToCompareAgainst(): void
    {
        $result = (new ContextSnapshot())->with('a', ['1']);

        self::assertSame(['a' => ['1']], $result->data());
        self::assertArrayHasKey('a', $result->defaults());
    }

    public function testWithKeepsAnExistingDefault(): void
    {
        $snapshot = new ContextSnapshot(['a' => ['1', '2']], ['a' => []]);

        self::assertSame(['a' => []], $snapshot->with('a', ['1'])->defaults());
    }

    public function testTheOriginalIsNeverChanged(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1], ['a' => 0]);

        $snapshot->without('a');
        $snapshot->with('a', 9);

        self::assertSame(['a' => 1], $snapshot->data());
    }

    public function testEqualityComparesBothHalves(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1], ['a' => 0]);

        self::assertTrue($snapshot->equals(new ContextSnapshot(['a' => 1], ['a' => 0])));
        self::assertFalse($snapshot->equals(new ContextSnapshot(['a' => 1], ['a' => 1])));
        self::assertFalse($snapshot->equals(new ContextSnapshot(['a' => 2], ['a' => 0])));
    }

    public function testMissingKeysReadAsNullRatherThanWarning(): void
    {
        $snapshot = new ContextSnapshot();

        self::assertFalse($snapshot->has('a'));
        self::assertNull($snapshot->valueOf('a'));
    }
}
