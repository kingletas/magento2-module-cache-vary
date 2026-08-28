<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary\Rule;

use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\Rule\AllowlistedValues;
use Commerce\CacheVary\Test\Unit\Fake\ArrayScopeConfig;
use PHPUnit\Framework\TestCase;

final class AllowlistedValuesTest extends TestCase
{
    private const SECTION = 'commerce_cachevary';
    private const PATH = 'policy/cacheable_customer_segments';
    private const KEY = 'customer_segment';

    public function testValuesOutsideTheAllowlistStopSplittingTheCache(): void
    {
        $result = $this->rule('9')->apply($this->snapshot(['9', '10', '12']));

        self::assertSame([self::KEY => ['9']], $result->data());
    }

    /**
     * The segment query has no ORDER BY, so two customers can hold the same set in a different order.
     */
    public function testTheKeptValuesAreSortedSoTheOrderCannotSplitTheCache(): void
    {
        $ordered = $this->rule('9,10,12')->apply($this->snapshot(['12', '9', '10']));
        $reversed = $this->rule('9,10,12')->apply($this->snapshot(['10', '12', '9']));

        self::assertSame([self::KEY => ['10', '12', '9']], $ordered->data());
        self::assertTrue($ordered->equals($reversed));
    }

    public function testDuplicatesCollapse(): void
    {
        $result = $this->rule('9')->apply($this->snapshot(['9', '9']));

        self::assertSame([self::KEY => ['9']], $result->data());
    }

    public function testAnEmptyAllowlistDropsTheKeyEntirely(): void
    {
        $result = $this->rule('')->apply($this->snapshot(['9', '12']));

        self::assertFalse($result->has(self::KEY));
    }

    public function testNothingMatchingTheAllowlistDropsTheKeyEntirely(): void
    {
        $result = $this->rule('7')->apply($this->snapshot(['9', '12']));

        self::assertFalse($result->has(self::KEY));
    }

    public function testAScalarValueIsTreatedAsASetOfOne(): void
    {
        $snapshot = new ContextSnapshot([self::KEY => '9'], [self::KEY => []]);

        self::assertSame([self::KEY => ['9']], $this->rule('9,10')->apply($snapshot)->data());
    }

    public function testAMissingKeyIsNotAnError(): void
    {
        $snapshot = new ContextSnapshot(['customer_group' => '1'], ['customer_group' => 0]);

        self::assertTrue($this->rule('9')->apply($snapshot)->equals($snapshot));
    }

    /**
     * n allowed values can appear in any combination, so the ceiling is 2^n.
     */
    public function testTheCeilingIsTheNumberOfCombinationsTheAllowlistPermits(): void
    {
        self::assertSame(1, $this->rule('')->ceiling());
        self::assertSame(2, $this->rule('9')->ceiling());
        self::assertSame(8, $this->rule('9,10,12')->ceiling());
    }

    public function testAnAllowlistTooLongToCountReportsUnbounded(): void
    {
        self::assertNull($this->rule(implode(',', range(1, 31)))->ceiling());
    }

    public function testItDescribesWhatItWillDo(): void
    {
        self::assertSame('allowlist empty — the key is dropped', $this->rule('')->describe());
        self::assertSame('restricted to 9, 12', $this->rule('9, 12')->describe());
    }

    private function rule(string $allowlist): AllowlistedValues
    {
        $config = new Config(
            new ArrayScopeConfig([self::SECTION . '/' . self::PATH => $allowlist]),
            self::SECTION
        );

        return new AllowlistedValues($config, self::KEY, self::PATH);
    }

    /**
     * @param string[] $segments
     */
    private function snapshot(array $segments): ContextSnapshot
    {
        return new ContextSnapshot([self::KEY => $segments], [self::KEY => []]);
    }
}
