<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit\Model\Vary;

use Commerce\CacheVary\Model\Vary\ContextSnapshot;
use Commerce\CacheVary\Model\Vary\ContextVaryHasher;
use Commerce\Foundation\Test\Support\ObjectManagerIsolation;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * The hash has to be the framework's own, salt included, or Varnish keys on something Magento will not reproduce.
 */
final class ContextVaryHasherTest extends TestCase
{
    use ObjectManagerIsolation;

    private const SALT = 'a-crypt-key';

    protected function setUp(): void
    {
        // `Http\Context` reaches for the deployment config itself, to salt the hash.
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->with('crypt/key')->willReturn(self::SALT);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($deploymentConfig);

        $this->useObjectManager($objectManager);
    }

    protected function tearDown(): void
    {
        $this->releaseObjectManager();
    }

    public function testItMatchesTheFrameworkHashOfTheSameData(): void
    {
        $data = ['customer_group' => '1', 'customer_logged_in' => true];

        $hash = $this->hasher()->hash(
            new ContextSnapshot($data, ['customer_group' => 0, 'customer_logged_in' => false])
        );

        ksort($data);
        self::assertSame(hash('sha256', (string) json_encode($data) . '|' . self::SALT), $hash);
    }

    /**
     * What a guest produces, and why a guest sends no cookie at all.
     */
    public function testASnapshotHoldingOnlyDefaultsHashesToNothing(): void
    {
        $snapshot = new ContextSnapshot(
            ['customer_group' => 0, 'customer_logged_in' => false],
            ['customer_group' => 0, 'customer_logged_in' => false]
        );

        self::assertNull($this->hasher()->hash($snapshot));
    }

    public function testAnEmptySnapshotHashesToNothing(): void
    {
        self::assertNull($this->hasher()->hash(new ContextSnapshot()));
    }

    /**
     * Two snapshots differing only in key order are the same cache entry.
     */
    public function testKeyOrderDoesNotChangeTheHash(): void
    {
        $hasher = $this->hasher();

        $first = $hasher->hash(new ContextSnapshot(['a' => '1', 'b' => '2'], ['a' => 0, 'b' => 0]));
        $second = $hasher->hash(new ContextSnapshot(['b' => '2', 'a' => '1'], ['b' => 0, 'a' => 0]));

        self::assertSame($first, $second);
    }

    private function hasher(): ContextVaryHasher
    {
        return new ContextVaryHasher(new Json());
    }
}
