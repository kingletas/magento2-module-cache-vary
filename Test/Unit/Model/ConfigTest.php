<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model;

use Commerce\CacheVary\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const SECTION = 'commerce_cachevary';

    public function testTheSwitchReadsFromItsOwnSection(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(self::SECTION . '/policy/enabled')
            ->willReturn(true);

        $this->assertTrue((new Config($scopeConfig, self::SECTION))->isEnabled());
    }

    public function testTheSwitchIsOffWhenNothingSetsIt(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse((new Config($scopeConfig, self::SECTION))->isEnabled());
    }

    public function testTheBudgetFallsBackWhenUnsetOrNonsense(): void
    {
        $this->assertSame(8, $this->config(null)->getBucketBudget());
        $this->assertSame(8, $this->config('0')->getBucketBudget());
        $this->assertSame(8, $this->config('lots')->getBucketBudget());
        $this->assertSame(32, $this->config('32')->getBucketBudget());
    }

    private function config(mixed $budget): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(self::SECTION . '/policy/bucket_budget')
            ->willReturn($budget);

        return new Config($scopeConfig, self::SECTION);
    }
}
