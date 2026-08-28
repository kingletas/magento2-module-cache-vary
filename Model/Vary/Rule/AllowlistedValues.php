<?php
/**
 * AllowlistedValues.php
 *
 * @package     Commerce_CacheVary
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Model\Vary\Rule;

use Commerce\CacheVary\Api\VaryRuleInterface;
use Commerce\CacheVary\Model\Config;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;

/**
 * Narrows one key to the values an operator has declared cache-relevant, sorted.
 */
class AllowlistedValues implements VaryRuleInterface
{
    /**
     * Beyond this many allowed values the combination count stops being a useful number.
     */
    private const MAX_EXPONENT = 30;

    public function __construct(
        private readonly Config $config,
        private readonly string $key,
        private readonly string $configPath
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(ContextSnapshot $snapshot, ?int $storeId = null): ContextSnapshot
    {
        if (!$snapshot->has($this->key)) {
            return $snapshot;
        }

        $kept = $this->keep($snapshot->valueOf($this->key), $storeId);

        return $kept === [] ? $snapshot->without($this->key) : $snapshot->with($this->key, $kept);
    }

    public function ceiling(?int $storeId = null): ?int
    {
        $allowed = count($this->allowed($storeId));

        return $allowed > self::MAX_EXPONENT ? null : 2 ** $allowed;
    }

    public function describe(?int $storeId = null): string
    {
        $allowed = $this->allowed($storeId);

        return $allowed === []
            ? 'allowlist empty — the key is dropped'
            : 'restricted to ' . implode(', ', $allowed);
    }

    /**
     * Sorted so two customers holding the same values in a different order share a cache entry.
     *
     * @return string[]
     */
    private function keep(mixed $value, ?int $storeId): array
    {
        $present = array_map('strval', is_array($value) ? $value : [$value]);
        $kept = array_values(array_unique(array_intersect($present, $this->allowed($storeId))));

        sort($kept, SORT_STRING);

        return $kept;
    }

    /**
     * @return string[]
     */
    private function allowed(?int $storeId): array
    {
        return $this->config->getList($this->configPath, $storeId);
    }
}
