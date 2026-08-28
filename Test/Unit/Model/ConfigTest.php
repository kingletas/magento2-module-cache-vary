<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model;

use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Test\Unit\Fake\ArrayScopeConfig;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const SECTION = 'commerce_cachevary';

    public function testTheSwitchReadsFromItsOwnSection(): void
    {
        self::assertTrue($this->config([self::SECTION . '/policy/enabled' => '1'])->isEnabled());
        self::assertFalse($this->config([self::SECTION . '/policy/enabled' => '0'])->isEnabled());
        self::assertFalse($this->config([])->isEnabled());
    }

    public function testTheBudgetFallsBackWhenUnsetOrNonsense(): void
    {
        self::assertSame(8, $this->config([])->getBucketBudget());
        self::assertSame(8, $this->config([self::SECTION . '/policy/bucket_budget' => '0'])->getBucketBudget());
        self::assertSame(8, $this->config([self::SECTION . '/policy/bucket_budget' => 'lots'])->getBucketBudget());
        self::assertSame(32, $this->config([self::SECTION . '/policy/bucket_budget' => '32'])->getBucketBudget());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function config(array $values): Config
    {
        return new Config(new ArrayScopeConfig($values), self::SECTION);
    }
}
