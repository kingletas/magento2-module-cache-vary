<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary\Rule;

use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\Rule\AllowlistedValues;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class AllowlistedValuesTest extends TestCase
{
    private const SECTION = 'commerce_cachevary';
    private const PATH = 'policy/cacheable_customer_segments';
    private const KEY = 'customer_segment';

    public function testValuesOutsideTheAllowlistStopSplittingTheCache(): void
    {
        $result = $this->rule('9')->apply($this->snapshot(['9', '10', '12']));

        $this->assertSame([self::KEY => ['9']], $result->data());
    }

    /**
     * The segment query has no ORDER BY, so two customers can hold the same set in a different order.
     */
    public function testTheKeptValuesAreSortedSoTheOrderCannotSplitTheCache(): void
    {
        $ordered = $this->rule('9,10,12')->apply($this->snapshot(['12', '9', '10']));
        $reversed = $this->rule('9,10,12')->apply($this->snapshot(['10', '12', '9']));

        $this->assertSame([self::KEY => ['10', '12', '9']], $ordered->data());
        $this->assertTrue($ordered->equals($reversed));
    }

    public function testDuplicatesCollapse(): void
    {
        $result = $this->rule('9')->apply($this->snapshot(['9', '9']));

        $this->assertSame([self::KEY => ['9']], $result->data());
    }

    public function testAnEmptyAllowlistDropsTheKeyEntirely(): void
    {
        $result = $this->rule('')->apply($this->snapshot(['9', '12']));

        $this->assertFalse($result->has(self::KEY));
    }

    public function testNothingMatchingTheAllowlistDropsTheKeyEntirely(): void
    {
        $result = $this->rule('7')->apply($this->snapshot(['9', '12']));

        $this->assertFalse($result->has(self::KEY));
    }

    public function testAScalarValueIsTreatedAsASetOfOne(): void
    {
        $snapshot = new ContextSnapshot([self::KEY => '9'], [self::KEY => []]);

        $this->assertSame([self::KEY => ['9']], $this->rule('9,10')->apply($snapshot)->data());
    }

    public function testAMissingKeyIsNotAnError(): void
    {
        $snapshot = new ContextSnapshot(['customer_group' => '1'], ['customer_group' => 0]);

        $this->assertTrue($this->rule('9')->apply($snapshot)->equals($snapshot));
    }

    /**
     * n allowed values can appear in any combination, so the ceiling is 2^n.
     */
    public function testTheCeilingIsTheNumberOfCombinationsTheAllowlistPermits(): void
    {
        $this->assertSame(1, $this->rule('')->ceiling());
        $this->assertSame(2, $this->rule('9')->ceiling());
        $this->assertSame(8, $this->rule('9,10,12')->ceiling());
    }

    public function testAnAllowlistTooLongToCountReportsUnbounded(): void
    {
        $this->assertNull($this->rule(implode(',', range(1, 31)))->ceiling());
    }

    public function testItDescribesWhatItWillDo(): void
    {
        $this->assertSame('allowlist empty — the key is dropped', $this->rule('')->describe());
        $this->assertSame('restricted to 9, 12', $this->rule('9, 12')->describe());
    }

    private function rule(string $allowlist): AllowlistedValues
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(self::SECTION . '/' . self::PATH)
            ->willReturn($allowlist);

        return new AllowlistedValues(new Config($scopeConfig, self::SECTION), self::KEY, self::PATH);
    }

    /**
     * @param string[] $segments
     */
    private function snapshot(array $segments): ContextSnapshot
    {
        return new ContextSnapshot([self::KEY => $segments], [self::KEY => []]);
    }
}
