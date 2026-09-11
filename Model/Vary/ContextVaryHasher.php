<?php
/**
 * @package   Kingletas_CacheVary
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CacheVary\Model\Vary;

use Kingletas\CacheVary\Api\VaryHasherInterface;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Hashes through a throwaway context, so the salt and the algorithm stay the framework's.
 */
class ContextVaryHasher implements VaryHasherInterface
{
    public function __construct(private readonly Json $serializer)
    {
    }

    public function hash(ContextSnapshot $snapshot): ?string
    {
        return (new Context($snapshot->data(), $snapshot->defaults(), $this->serializer))->getVaryString();
    }
}
