<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Model\Vary;

/**
 * An immutable copy of what `Http\Context::toArray()` holds.
 */
class ContextSnapshot
{
    /**
     * @param mixed[] $data
     * @param mixed[] $default
     */
    public function __construct(
        private readonly array $data = [],
        private readonly array $default = []
    ) {
    }

    /**
     * @return mixed[]
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return mixed[]
     */
    public function defaults(): array
    {
        return $this->default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function valueOf(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Drop a key so it can no longer reach the vary string.
     */
    public function without(string $key): self
    {
        $data = $this->data;
        $default = $this->default;

        unset($data[$key], $default[$key]);

        return new self($data, $default);
    }

    /**
     * Replace a key's value, keeping a default so `getData()` has one to compare against.
     */
    public function with(string $key, mixed $value): self
    {
        $data = $this->data;
        $default = $this->default;

        $data[$key] = $value;

        if (!array_key_exists($key, $default)) {
            $default[$key] = null;
        }

        return new self($data, $default);
    }

    public function equals(self $other): bool
    {
        return $this->data === $other->data && $this->default === $other->default;
    }
}
