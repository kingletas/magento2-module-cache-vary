<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Unit\Plugin\Framework\App\Http;

use Kingletas\CacheVary\Api\PolicyGuardInterface;
use Kingletas\CacheVary\Api\VaryHasherInterface;
use Kingletas\CacheVary\Model\Vary\ContextSnapshot;
use Kingletas\CacheVary\Model\Vary\GuardDecision;
use Kingletas\CacheVary\Model\Vary\GuardOutcome;
use Kingletas\CacheVary\Model\Vary\Rule\ExcludedKey;
use Kingletas\CacheVary\Model\Vary\VaryPolicy;
use Kingletas\CacheVary\Plugin\Framework\App\Http\VaryStringPlugin;
use Magento\Framework\App\Http\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VaryStringPluginTest extends TestCase
{
    private const UNTOUCHED = 'the framework hash';
    private const HASHED = 'the policy hash';

    private VaryHasherInterface|MockObject $hasher;

    protected function setUp(): void
    {
        $this->hasher = $this->createMock(VaryHasherInterface::class);
    }

    public function testAGuardThatRefusesLeavesTheFrameworkToIt(): void
    {
        $this->hasher->expects($this->never())->method('hash');

        $result = $this->plugin(GuardOutcome::NotApplied)->aroundGetVaryString(
            $this->context(['customer_segment' => ['9']], ['customer_segment' => []]),
            static fn (): string => self::UNTOUCHED
        );

        $this->assertSame(self::UNTOUCHED, $result);
    }

    /**
     * A guest carries nothing the policy governs, so it must not pay for a second hash.
     */
    public function testAContextThePolicyDoesNotChangeIsLeftToTheFramework(): void
    {
        $this->hasher->expects($this->never())->method('hash');

        $result = $this->plugin()->aroundGetVaryString(
            $this->context(['customer_group' => '1'], ['customer_group' => 0]),
            static fn (): string => self::UNTOUCHED
        );

        $this->assertSame(self::UNTOUCHED, $result);
    }

    public function testAGovernedKeyIsHashedOutOfTheFilteredCopy(): void
    {
        $this->hasher->expects($this->once())
            ->method('hash')
            ->with($this->callback(
                static fn (ContextSnapshot $snapshot): bool => $snapshot->data() === ['customer_group' => '1']
            ))
            ->willReturn(self::HASHED);

        $result = $this->plugin()->aroundGetVaryString(
            $this->context(
                ['customer_group' => '1', 'customer_segment' => ['9', '12']],
                ['customer_group' => 0, 'customer_segment' => []]
            ),
            static fn (): string => self::UNTOUCHED
        );

        $this->assertSame(self::HASHED, $result);
    }

    /**
     * Other code reads the live context, so filtering must not reach it.
     */
    public function testTheContextItselfIsNeverChanged(): void
    {
        $context = $this->context(['customer_segment' => ['9']], ['customer_segment' => []]);

        $this->plugin()->aroundGetVaryString($context, static fn (): string => self::UNTOUCHED);

        $this->assertSame(['9'], $context->getValue('customer_segment'));
    }

    /**
     * Two customers differing only in a segment must land on the same cache entry.
     */
    public function testTwoSegmentSetsCollapseToOneKey(): void
    {
        $hashed = [];
        $this->hasher->expects($this->exactly(2))
            ->method('hash')
            ->willReturnCallback(function (ContextSnapshot $snapshot) use (&$hashed): string {
                $hashed[] = $snapshot->data();

                return self::HASHED;
            });

        $plugin = $this->plugin();
        $proceed = static fn (): string => self::UNTOUCHED;

        $plugin->aroundGetVaryString(
            $this->context(
                ['customer_group' => '1', 'customer_segment' => ['9']],
                ['customer_group' => 0, 'customer_segment' => []]
            ),
            $proceed
        );
        $plugin->aroundGetVaryString(
            $this->context(
                ['customer_group' => '1', 'customer_segment' => ['9', '10', '12']],
                ['customer_group' => 0, 'customer_segment' => []]
            ),
            $proceed
        );

        $this->assertSame($hashed[0], $hashed[1]);
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $default
     */
    private function context(array $data, array $default): Context
    {
        $context = $this->createMock(Context::class);
        $context->method('toArray')->willReturn(['data' => $data, 'default' => $default]);
        $context->method('getValue')->willReturnCallback(
            static fn (string $name): mixed => $data[$name] ?? $default[$name] ?? null
        );

        return $context;
    }

    private function plugin(GuardOutcome $outcome = GuardOutcome::Applies): VaryStringPlugin
    {
        $guard = $this->createMock(PolicyGuardInterface::class);
        $guard->method('decide')->willReturn(new GuardDecision($outcome, 'stubbed'));

        return new VaryStringPlugin(
            new VaryPolicy([new ExcludedKey('customer_segment')]),
            $this->hasher,
            $guard
        );
    }
}
