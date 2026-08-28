<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\Rule\ExcludedKey;
use Commerce\CacheVary\Model\Vary\VaryPolicy;
use Commerce\CacheVary\Test\Unit\Fake\FixedCeilingRule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VaryPolicyTest extends TestCase
{
    public function testNoRulesLeavesTheSnapshotAlone(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1], ['a' => 0]);

        self::assertTrue((new VaryPolicy())->apply($snapshot)->equals($snapshot));
    }

    public function testEveryRuleIsApplied(): void
    {
        $policy = new VaryPolicy([new ExcludedKey('a'), new ExcludedKey('b')]);

        $result = $policy->apply(new ContextSnapshot(['a' => 1, 'b' => 2, 'c' => 3], []));

        self::assertSame(['c' => 3], $result->data());
    }

    public function testTheCeilingIsTheProductOfTheRules(): void
    {
        $policy = new VaryPolicy([new FixedCeilingRule('a', 4), new FixedCeilingRule('b', 8)]);

        self::assertSame(32, $policy->ceiling());
    }

    public function testNoRulesPermitsOneVariant(): void
    {
        self::assertSame(1, (new VaryPolicy())->ceiling());
    }

    public function testOneUnboundedRuleMakesTheWholePolicyUnbounded(): void
    {
        $policy = new VaryPolicy([new FixedCeilingRule('a', 2), new FixedCeilingRule('b', null)]);

        self::assertNull($policy->ceiling());
    }

    /**
     * A product past the counting limit is not a number worth printing.
     */
    public function testAProductTooLargeToCountReportsUnbounded(): void
    {
        $policy = new VaryPolicy([new FixedCeilingRule('a', 2 ** 20), new FixedCeilingRule('b', 2 ** 20)]);

        self::assertNull($policy->ceiling());
    }

    public function testSomethingThatIsNotARuleIsRefusedAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('customer_segment');

        /** @phpstan-ignore-next-line the wrong type is the case under test */
        new VaryPolicy(['customer_segment' => 'not a rule']);
    }
}
