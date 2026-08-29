<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Model\Vary;

use Commerce\CacheVary\Api\VaryPolicyInterface;
use Commerce\CacheVary\Api\VaryRuleInterface;
use InvalidArgumentException;

/**
 * Applies every declared rule to a context snapshot, in `di.xml` order.
 */
class VaryPolicy implements VaryPolicyInterface
{
    /**
     * A rule set with two rules for one key is a configuration mistake rather than a merge.
     */
    private const MAX_EXPONENT = 30;

    /**
     * @var VaryRuleInterface[]
     */
    private readonly array $rules;

    /**
     * @param VaryRuleInterface[] $rules
     */
    public function __construct(array $rules = [])
    {
        foreach ($rules as $name => $rule) {
            if (!$rule instanceof VaryRuleInterface) {
                throw new InvalidArgumentException(
                    sprintf('Rule "%s" does not implement %s.', (string) $name, VaryRuleInterface::class)
                );
            }
        }

        $this->rules = array_values($rules);
    }

    public function apply(ContextSnapshot $snapshot, ?int $storeId = null): ContextSnapshot
    {
        foreach ($this->rules as $rule) {
            $snapshot = $rule->apply($snapshot, $storeId);
        }

        return $snapshot;
    }

    /**
     * @return VaryRuleInterface[]
     */
    public function rules(): array
    {
        return $this->rules;
    }

    public function ceiling(?int $storeId = null): ?int
    {
        $product = 1;

        foreach ($this->rules as $rule) {
            $ceiling = $rule->ceiling($storeId);

            if ($ceiling === null || $product > (2 ** self::MAX_EXPONENT) / max($ceiling, 1)) {
                return null;
            }

            $product *= $ceiling;
        }

        return $product;
    }
}
