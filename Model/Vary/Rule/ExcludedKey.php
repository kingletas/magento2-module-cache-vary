<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Model\Vary\Rule;

use Kingletas\CacheVary\Api\VaryRuleInterface;
use Kingletas\CacheVary\Model\Vary\ContextSnapshot;

/**
 * Keeps one key out of the cache key entirely, for content that never varies by it.
 */
class ExcludedKey implements VaryRuleInterface
{
    public function __construct(private readonly string $key)
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(ContextSnapshot $snapshot, ?int $storeId = null): ContextSnapshot
    {
        return $snapshot->without($this->key);
    }

    public function ceiling(?int $storeId = null): ?int
    {
        return 1;
    }

    public function describe(?int $storeId = null): string
    {
        return 'excluded — never reaches the cache key';
    }
}
