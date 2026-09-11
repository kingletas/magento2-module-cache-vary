<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Performance;

use Kingletas\CacheVary\Model\Config;
use Kingletas\CacheVary\Model\Vary\Rule\AllowlistedValues;
use Kingletas\CacheVary\Model\Vary\VaryPolicy;
use Kingletas\CacheVary\Plugin\Framework\App\Http\VaryStringPlugin;
use Kingletas\CacheVary\Model\Vary\PolicyGuard;
use Kingletas\CacheVary\Api\VaryHasherInterface;
use Magento\PageCache\Model\Config as PageCacheConfig;
use Kingletas\Foundation\Test\Support\BudgetAssertions;
use Kingletas\Foundation\Test\Support\CountingScopeConfig;
use Magento\Framework\App\Http\Context;
use PHPUnit\Framework\TestCase;

/**
 * What this module costs a request, given that it runs on every one of them.
 */
class PolicyCostTest extends TestCase
{
    use BudgetAssertions;

    private const SECTION = 'kingletas_cachevary';
    private const PATH = 'policy/cacheable_customer_segments';

    /**
     * A page with many context values must not cost more per value.
     */
    public function testTheCostDoesNotGrowWithTheNumberOfContextValues(): void
    {
        $this->assertConstantCost(
            'config reads while filtering a context',
            fn (int $keys): int => $this->filter($keys)->reads()
        );
    }

    /**
     * A shopper in many segments must not cost more than one in a few.
     */
    public function testTheCostDoesNotGrowWithTheNumberOfSegments(): void
    {
        $this->assertConstantCost(
            'config reads while filtering a customer holding many segments',
            fn (int $segments): int => $this->filter(1, $segments)->reads()
        );
    }

    /**
     * The switch, then the allowlist once per governed key.
     */
    public function testTheWholeFilterIsDecidedByTwoConfigReads(): void
    {
        $scopeConfig = $this->filter(20, 20);

        $this->assertCostAtMost(
            'filtering one context',
            2,
            $scopeConfig->reads(),
            $scopeConfig->summary()
        );
    }

    /**
     * A switched-off policy reads the switch and nothing else.
     */
    public function testASwitchedOffPolicyCostsOneRead(): void
    {
        $scopeConfig = $this->filter(20, 20, enabled: false);

        $this->assertCostAtMost('a switched-off policy', 1, $scopeConfig->reads(), $scopeConfig->summary());
    }

    private function filter(int $keys, int $segments = 3, bool $enabled = true): CountingScopeConfig
    {
        $scopeConfig = new CountingScopeConfig([
            self::SECTION . '/policy/enabled' => $enabled ? '1' : '0',
            self::SECTION . '/' . self::PATH => '9,10,12',
        ]);

        $config = new Config($scopeConfig, self::SECTION);
        $pageCache = $this->createMock(PageCacheConfig::class);
        $pageCache->method('isEnabled')->willReturn(true);
        $pageCache->method('getType')->willReturn(PageCacheConfig::VARNISH);

        $policy = new VaryPolicy([new AllowlistedValues($config, 'customer_segment', self::PATH)]);
        $plugin = new VaryStringPlugin(
            $policy,
            $this->createMock(VaryHasherInterface::class),
            new PolicyGuard($config, $pageCache, $policy, [PageCacheConfig::VARNISH], ['customer_group'])
        );

        $data = ['customer_segment' => array_map('strval', range(1, $segments))];
        $default = ['customer_segment' => []];

        for ($i = 0; $i < $keys; $i++) {
            $data['filler_' . $i] = 'value';
            $default['filler_' . $i] = '';
        }

        $context = $this->createMock(Context::class);
        $context->method('toArray')->willReturn(['data' => $data, 'default' => $default]);

        $plugin->aroundGetVaryString($context, static fn (): string => 'framework');

        return $scopeConfig;
    }
}
