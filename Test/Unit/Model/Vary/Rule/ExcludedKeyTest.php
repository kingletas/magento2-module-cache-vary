<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary\Rule;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\Rule\ExcludedKey;
use PHPUnit\Framework\TestCase;

class ExcludedKeyTest extends TestCase
{
    public function testItRemovesItsOwnKeyAndNothingElse(): void
    {
        $snapshot = new ContextSnapshot(
            ['customer_group' => '1', 'customer_segment' => ['9']],
            ['customer_group' => 0, 'customer_segment' => []]
        );

        $result = (new ExcludedKey('customer_segment'))->apply($snapshot);

        $this->assertSame(['customer_group' => '1'], $result->data());
    }

    public function testAMissingKeyIsNotAnError(): void
    {
        $snapshot = new ContextSnapshot(['customer_group' => '1'], ['customer_group' => 0]);

        $this->assertTrue((new ExcludedKey('customer_segment'))->apply($snapshot)->equals($snapshot));
    }

    public function testAnExcludedKeyContributesOneVariant(): void
    {
        $this->assertSame(1, (new ExcludedKey('customer_segment'))->ceiling());
    }
}
