<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Test\Behaviour;

use Kingletas\CacheVary\Model\Config;
use Kingletas\CacheVary\Model\Vary\ContextVaryHasher;
use Kingletas\CacheVary\Model\Vary\Rule\AllowlistedValues;
use Kingletas\CacheVary\Model\Vary\VaryPolicy;
use Kingletas\CacheVary\Plugin\Framework\App\Http\VaryStringPlugin;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Kingletas\CacheVary\Model\Vary\PolicyGuard;
use Magento\PageCache\Model\Config as PageCacheConfig;
use Kingletas\Foundation\Test\Support\ObjectManagerIsolation;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * How many copies of one page a spread of segment memberships makes the edge hold.
 */
class SegmentFragmentationTest extends TestCase
{
    use ObjectManagerIsolation;

    private const SECTION = 'kingletas_cachevary';
    private const PATH = 'policy/cacheable_customer_segments';
    private const SEGMENT_KEY = 'customer_segment';

    /**
     * A synthetic spread of memberships: every singleton, a chain of pairs, some triples, and a
     * few wider sets. Breadth is the point — the assertions are about how many survive.
     *
     * @var string[][]
     */
    private const COMBINATIONS = [
        [],
        ['1'], ['2'], ['3'], ['4'], ['5'], ['6'], ['7'],
        ['8'], ['9'], ['10'], ['11'], ['12'], ['13'], ['14'],
        ['1', '2'], ['1', '3'], ['2', '3'], ['3', '4'], ['4', '5'], ['5', '6'], ['6', '7'],
        ['7', '8'], ['8', '9'], ['9', '10'], ['10', '11'], ['11', '12'], ['12', '13'], ['13', '14'],
        ['1', '2', '3'], ['4', '5', '6'], ['7', '8', '9'], ['9', '10', '12'], ['10', '11', '12'],
        ['1', '2', '3', '4'], ['9', '10', '11', '12'],
        ['9', '10', '11', '12', '13'],
    ];

    protected function setUp(): void
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->with('crypt/key')->willReturn('a-crypt-key');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($deploymentConfig);

        $this->useObjectManager($objectManager);
    }

    protected function tearDown(): void
    {
        $this->releaseObjectManager();
    }

    /**
     * Stock behaviour: every distinct membership is its own cache entry.
     */
    public function testWithoutThePolicyEverySegmentCombinationIsItsOwnCacheEntry(): void
    {
        $this->assertCount(
            count(self::COMBINATIONS),
            array_unique($this->varyStrings(enabled: false, allowlist: ''))
        );
    }

    public function testAnEmptyAllowlistCollapsesThemAllOntoOne(): void
    {
        $this->assertCount(1, array_unique($this->varyStrings(enabled: true, allowlist: '')));
    }

    /**
     * One segment that genuinely changes cached HTML costs one extra entry, not forty.
     */
    public function testAllowlistingOneSegmentSplitsTheCacheExactlyOnce(): void
    {
        $this->assertCount(2, array_unique($this->varyStrings(enabled: true, allowlist: '9')));
    }

    public function testAllowlistingThreeSegmentsStaysWithinTheirCombinations(): void
    {
        $distinct = array_unique($this->varyStrings(enabled: true, allowlist: '9,10,12'));

        $this->assertLessThanOrEqual(2 ** 3, count($distinct));
    }

    /**
     * A guest contributes nothing to the key, with the policy on or off.
     */
    public function testAGuestStillSendsNoKeyAtAll(): void
    {
        $context = new HttpContext([], [], new Json());
        $context->setValue(CustomerContext::CONTEXT_GROUP, '0', 0);
        $context->setValue(CustomerContext::CONTEXT_AUTH, false, false);

        $plugin = $this->plugin(enabled: true, allowlist: '');

        $this->assertNull($plugin->aroundGetVaryString($context, static fn () => $context->getVaryString()));
    }

    /**
     * @return array<int, string|null>
     */
    private function varyStrings(bool $enabled, string $allowlist): array
    {
        $plugin = $this->plugin($enabled, $allowlist);
        $strings = [];

        foreach (self::COMBINATIONS as $segments) {
            $context = new HttpContext([], [], new Json());
            $context->setValue(CustomerContext::CONTEXT_GROUP, '1', 0);
            $context->setValue(CustomerContext::CONTEXT_AUTH, true, false);
            $context->setValue(self::SEGMENT_KEY, $segments, []);

            $strings[] = $plugin->aroundGetVaryString($context, static fn () => $context->getVaryString());
        }

        return $strings;
    }

    private function plugin(bool $enabled, string $allowlist): VaryStringPlugin
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')
            ->with(self::SECTION . '/policy/enabled')
            ->willReturn($enabled);
        $scopeConfig->method('getValue')
            ->with(self::SECTION . '/' . self::PATH)
            ->willReturn($allowlist);

        $config = new Config($scopeConfig, self::SECTION);

        $pageCache = $this->createMock(PageCacheConfig::class);
        $pageCache->method('isEnabled')->willReturn(true);
        $pageCache->method('getType')->willReturn(PageCacheConfig::VARNISH);

        $policy = new VaryPolicy([new AllowlistedValues($config, self::SEGMENT_KEY, self::PATH)]);

        return new VaryStringPlugin(
            $policy,
            new ContextVaryHasher(new Json()),
            new PolicyGuard($config, $pageCache, $policy, [PageCacheConfig::VARNISH], ['customer_group'])
        );
    }
}
