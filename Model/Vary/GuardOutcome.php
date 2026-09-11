<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Model\Vary;

/**
 * What the guard decided, and how much it matters.
 */
enum GuardOutcome: string
{
    /**
     * The policy may rewrite the cache key.
     */
    case Applies = 'applies';

    /**
     * A deliberate state — switched off, cache off, or a caching application this policy leaves alone.
     */
    case NotApplied = 'not_applied';

    /**
     * A wiring mistake nobody chose, so it is reported whether or not anyone asked for a gate.
     */
    case Misconfigured = 'misconfigured';
}
