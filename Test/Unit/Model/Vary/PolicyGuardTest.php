<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary;

use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Model\Vary\GuardOutcome;
use Commerce\CacheVary\Model\Vary\PolicyGuard;
use Commerce\CacheVary\Model\Vary\Rule\ExcludedKey;
use Commerce\CacheVary\Model\Vary\VaryPolicy;
use Commerce\CacheVary\Test\Unit\Fake\ArrayScopeConfig;
use Magento\PageCache\Model\Config as PageCacheConfig;
use PHPUnit\Framework\TestCase;

class PolicyGuardTest extends TestCase
{
    private const SECTION = 'commerce_cachevary';

    public function testItAppliesOnVarnish(): void
    {
        $decision = $this->guard(enabled: true, fpcOn: true, type: PageCacheConfig::VARNISH)->decide();

        $this->assertTrue($decision->applies());
        $this->assertSame('narrowing the Varnish cache key', $decision->reason());
    }

    /**
     * The case this guard exists for, and the one Magento ships as the default.
     */
    public function testItRefusesOnTheBuiltInCache(): void
    {
        $decision = $this->guard(enabled: true, fpcOn: true, type: PageCacheConfig::BUILT_IN)->decide();

        $this->assertFalse($decision->applies());
        $this->assertSame(
            'the full-page cache is the built-in cache, which this policy is not verified against',
            $decision->reason()
        );
    }

    /**
     * Fastly is Varnish underneath, but adding it is a decision taken in di.xml rather than assumed.
     */
    public function testAnUnknownCachingApplicationIsRefusedAndNamedByNumber(): void
    {
        $decision = $this->guard(enabled: true, fpcOn: true, type: 42)->decide();

        $this->assertFalse($decision->applies());
        $this->assertStringContainsString('caching application 42', $decision->reason());
    }

    public function testAnAcceptedCachingApplicationCanBeAddedThroughWiring(): void
    {
        $guard = $this->guard(enabled: true, fpcOn: true, type: 42, accepted: [PageCacheConfig::VARNISH, 42]);

        $this->assertTrue($guard->decide()->applies());
    }

    public function testASwitchedOffPolicyNeverApplies(): void
    {
        $decision = $this->guard(enabled: false, fpcOn: true, type: PageCacheConfig::VARNISH)->decide();

        $this->assertFalse($decision->applies());
        $this->assertSame('the policy is switched off', $decision->reason());
    }

    public function testADisabledFullPageCacheNeverApplies(): void
    {
        $decision = $this->guard(enabled: true, fpcOn: false, type: PageCacheConfig::VARNISH)->decide();

        $this->assertFalse($decision->applies());
        $this->assertStringContainsString('full-page cache is off', $decision->reason());
    }

    /**
     * An empty wiring list means nothing is accepted, which is the safe direction.
     */
    public function testNoAcceptedApplicationsMeansItNeverApplies(): void
    {
        $guard = $this->guard(enabled: true, fpcOn: true, type: PageCacheConfig::VARNISH, accepted: []);

        $this->assertFalse($guard->decide()->applies());
    }

    /**
     * `di.xml` numbers arrive as integers or strings depending on how they are declared.
     */
    public function testTheAcceptedListIsComparedNumerically(): void
    {
        $guard = $this->guard(enabled: true, fpcOn: true, type: PageCacheConfig::VARNISH, accepted: ['2']);

        $this->assertTrue($guard->decide()->applies());
    }

    /**
     * Merging pricing tiers is not fragmentation, it is the wrong price, so the policy goes out entirely.
     */
    public function testARuleOnCustomerGroupTakesTheWholePolicyOutOfForce(): void
    {
        $guard = $this->guard(
            enabled: true,
            fpcOn: true,
            type: PageCacheConfig::VARNISH,
            rules: [new ExcludedKey('customer_group')]
        );

        $decision = $guard->decide();

        $this->assertFalse($decision->applies());
        $this->assertSame(GuardOutcome::Misconfigured, $decision->outcome());
        $this->assertStringContainsString('customer_group', $decision->reason());
    }

    public function testARuleOnLoggedInStateIsRefusedToo(): void
    {
        $guard = $this->guard(
            enabled: true,
            fpcOn: true,
            type: PageCacheConfig::VARNISH,
            rules: [new ExcludedKey('customer_logged_in')]
        );

        $this->assertSame(GuardOutcome::Misconfigured, $guard->decide()->outcome());
    }

    /**
     * A wiring mistake is reported even from a switched-off store, so it is fixed before going live.
     */
    public function testAProtectedKeyIsReportedEvenWhileThePolicyIsOff(): void
    {
        $guard = $this->guard(
            enabled: false,
            fpcOn: true,
            type: PageCacheConfig::VARNISH,
            rules: [new ExcludedKey('customer_group')]
        );

        $this->assertSame(GuardOutcome::Misconfigured, $guard->decide()->outcome());
    }

    public function testAnOrdinaryRuleAlongsideAProtectedOneDoesNotRescueIt(): void
    {
        $guard = $this->guard(
            enabled: true,
            fpcOn: true,
            type: PageCacheConfig::VARNISH,
            rules: [new ExcludedKey('customer_segment'), new ExcludedKey('customer_group')]
        );

        $this->assertSame(GuardOutcome::Misconfigured, $guard->decide()->outcome());
    }

    /**
     * @param int[]|string[]|null $accepted
     * @param \Commerce\CacheVary\Api\VaryRuleInterface[] $rules
     */
    private function guard(
        bool $enabled,
        bool $fpcOn,
        int $type,
        ?array $accepted = null,
        array $rules = []
    ): PolicyGuard {
        $config = new Config(
            new ArrayScopeConfig([self::SECTION . '/policy/enabled' => $enabled ? '1' : '0']),
            self::SECTION
        );

        $pageCache = $this->createMock(PageCacheConfig::class);
        $pageCache->method('isEnabled')->willReturn($fpcOn);
        $pageCache->method('getType')->willReturn($type);

        return new PolicyGuard(
            $config,
            $pageCache,
            new VaryPolicy($rules),
            $accepted ?? [PageCacheConfig::VARNISH],
            ['customer_group', 'customer_logged_in']
        );
    }
}
