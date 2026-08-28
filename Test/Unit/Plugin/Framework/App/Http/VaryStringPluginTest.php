<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Plugin\Framework\App\Http;

use Commerce\CacheVary\Model\Vary\Rule\ExcludedKey;
use Commerce\CacheVary\Model\Vary\VaryPolicy;
use Commerce\CacheVary\Plugin\Framework\App\Http\VaryStringPlugin;
use Commerce\CacheVary\Test\Unit\Fake\RecordingHasher;
use Commerce\CacheVary\Test\Unit\Fake\StubPolicyGuard;
use Magento\Framework\App\Http\Context;
use PHPUnit\Framework\TestCase;

final class VaryStringPluginTest extends TestCase
{
    private const UNTOUCHED = 'the framework hash';

    public function testAGuardThatRefusesLeavesTheFrameworkToIt(): void
    {
        $hasher = new RecordingHasher();

        $result = $this->plugin($hasher, applies: false)->aroundGetVaryString(
            $this->context(['customer_segment' => ['9']], ['customer_segment' => []]),
            static fn (): string => self::UNTOUCHED
        );

        self::assertSame(self::UNTOUCHED, $result);
        self::assertNull($hasher->received);
    }

    /**
     * A guest carries nothing the policy governs, so it must not pay for a second hash.
     */
    public function testAContextThePolicyDoesNotChangeIsLeftToTheFramework(): void
    {
        $hasher = new RecordingHasher();

        $result = $this->plugin($hasher)->aroundGetVaryString(
            $this->context(['customer_group' => '1'], ['customer_group' => 0]),
            static fn (): string => self::UNTOUCHED
        );

        self::assertSame(self::UNTOUCHED, $result);
        self::assertNull($hasher->received);
    }

    public function testAGovernedKeyIsHashedOutOfTheFilteredCopy(): void
    {
        $hasher = new RecordingHasher();

        $result = $this->plugin($hasher)->aroundGetVaryString(
            $this->context(
                ['customer_group' => '1', 'customer_segment' => ['9', '12']],
                ['customer_group' => 0, 'customer_segment' => []]
            ),
            static fn (): string => self::UNTOUCHED
        );

        self::assertNotSame(self::UNTOUCHED, $result);
        self::assertSame(['customer_group' => '1'], $hasher->received?->data());
    }

    /**
     * Other code reads the live context, so filtering must not reach it.
     */
    public function testTheContextItselfIsNeverChanged(): void
    {
        $context = $this->context(['customer_segment' => ['9']], ['customer_segment' => []]);

        $this->plugin(new RecordingHasher())->aroundGetVaryString($context, static fn (): string => self::UNTOUCHED);

        self::assertSame(['9'], $context->getValue('customer_segment'));
    }

    /**
     * Two customers differing only in a segment must land on the same cache entry.
     */
    public function testTwoSegmentSetsCollapseToOneKey(): void
    {
        $plugin = $this->plugin(new RecordingHasher());
        $proceed = static fn (): string => self::UNTOUCHED;

        $first = $plugin->aroundGetVaryString(
            $this->context(
                ['customer_group' => '1', 'customer_segment' => ['9']],
                ['customer_group' => 0, 'customer_segment' => []]
            ),
            $proceed
        );
        $second = $plugin->aroundGetVaryString(
            $this->context(
                ['customer_group' => '1', 'customer_segment' => ['9', '10', '12']],
                ['customer_group' => 0, 'customer_segment' => []]
            ),
            $proceed
        );

        self::assertSame($first, $second);
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

    private function plugin(RecordingHasher $hasher, bool $applies = true): VaryStringPlugin
    {
        return new VaryStringPlugin(
            new VaryPolicy([new ExcludedKey('customer_segment')]),
            $hasher,
            new StubPolicyGuard($applies)
        );
    }
}
