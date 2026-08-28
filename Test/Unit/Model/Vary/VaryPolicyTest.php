<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\Rule\ExcludedKey;
use Commerce\CacheVary\Api\VaryRuleInterface;
use Commerce\CacheVary\Model\Vary\VaryPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VaryPolicyTest extends TestCase
{
    public function testNoRulesLeavesTheSnapshotAlone(): void
    {
        $snapshot = new ContextSnapshot(['a' => 1], ['a' => 0]);

        $this->assertTrue((new VaryPolicy())->apply($snapshot)->equals($snapshot));
    }

    public function testEveryRuleIsApplied(): void
    {
        $policy = new VaryPolicy([new ExcludedKey('a'), new ExcludedKey('b')]);

        $result = $policy->apply(new ContextSnapshot(['a' => 1, 'b' => 2, 'c' => 3], []));

        $this->assertSame(['c' => 3], $result->data());
    }

    public function testTheCeilingIsTheProductOfTheRules(): void
    {
        $policy = new VaryPolicy([$this->ruleWithCeiling('a', 4), $this->ruleWithCeiling('b', 8)]);

        $this->assertSame(32, $policy->ceiling());
    }

    public function testNoRulesPermitsOneVariant(): void
    {
        $this->assertSame(1, (new VaryPolicy())->ceiling());
    }

    public function testOneUnboundedRuleMakesTheWholePolicyUnbounded(): void
    {
        $policy = new VaryPolicy([$this->ruleWithCeiling('a', 2), $this->ruleWithCeiling('b', null)]);

        $this->assertNull($policy->ceiling());
    }

    /**
     * A product past the counting limit is not a number worth printing.
     */
    public function testAProductTooLargeToCountReportsUnbounded(): void
    {
        $policy = new VaryPolicy([
            $this->ruleWithCeiling('a', 2 ** 20),
            $this->ruleWithCeiling('b', 2 ** 20),
        ]);

        $this->assertNull($policy->ceiling());
    }

    public function testSomethingThatIsNotARuleIsRefusedAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('customer_segment');

        /** @phpstan-ignore-next-line the wrong type is the case under test */
        new VaryPolicy(['customer_segment' => 'not a rule']);
    }

    private function ruleWithCeiling(string $key, ?int $ceiling): VaryRuleInterface
    {
        $rule = $this->createMock(VaryRuleInterface::class);
        $rule->method('key')->willReturn($key);
        $rule->method('ceiling')->willReturn($ceiling);
        $rule->method('apply')->willReturnArgument(0);

        return $rule;
    }
}
