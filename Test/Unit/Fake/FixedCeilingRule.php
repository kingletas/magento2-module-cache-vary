<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Fake;

use Commerce\CacheVary\Api\VaryRuleInterface;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;

/**
 * A rule that changes nothing and reports whatever ceiling the test needs.
 */
final class FixedCeilingRule implements VaryRuleInterface
{
    public function __construct(private readonly string $key, private readonly ?int $ceiling)
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(ContextSnapshot $snapshot, ?int $storeId = null): ContextSnapshot
    {
        return $snapshot;
    }

    public function ceiling(?int $storeId = null): ?int
    {
        return $this->ceiling;
    }

    public function describe(?int $storeId = null): string
    {
        return 'fixed';
    }
}
