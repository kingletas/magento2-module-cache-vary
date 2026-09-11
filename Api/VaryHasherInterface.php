<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Api;

use Kingletas\CacheVary\Model\Vary\ContextSnapshot;

/**
 * Turns a context snapshot into the string Varnish keys on.
 */
interface VaryHasherInterface
{
    /**
     * Null where the snapshot carries nothing but defaults, which is what a guest sends.
     */
    public function hash(ContextSnapshot $snapshot): ?string;
}
