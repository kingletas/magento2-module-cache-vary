<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Fake;

use Commerce\CacheVary\Api\VaryHasherInterface;
use Commerce\CacheVary\Model\Vary\ContextSnapshot;

/**
 * Hashes by serialising, so a test can read the snapshot it was handed.
 */
class RecordingHasher implements VaryHasherInterface
{
    public ?ContextSnapshot $received = null;

    public function hash(ContextSnapshot $snapshot): ?string
    {
        $this->received = $snapshot;

        return $snapshot->data() === [] ? null : json_encode($snapshot->data());
    }
}
