<?php
/**
 * @package   Commerce_CacheVary
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * A plugin on `Http\Context` declared in a global `di.xml` binds to nothing, so it goes per area.
 */
class PluginScopeTest extends TestCase
{
    private const INTERCEPTED = 'Magento\Framework\App\Http\Context';
    private const PLUGIN = 'Commerce\CacheVary\Plugin\Framework\App\Http\VaryStringPlugin';

    /**
     * @return string[][]
     */
    public static function areasThatComputeTheVary(): array
    {
        return [['frontend'], ['graphql']];
    }

    /**
     * @dataProvider areasThatComputeTheVary
     */
    public function testThePluginIsDeclaredInEachAreaThatHashesTheKey(string $area): void
    {
        $this->assertSame(
            self::PLUGIN,
            $this->pluginOn(sprintf('etc/%s/di.xml', $area)),
            sprintf('etc/%s/di.xml must plug %s.', $area, self::INTERCEPTED)
        );
    }

    /**
     * The mistake this test exists to catch, stated as an assertion.
     */
    public function testTheGlobalScopeDeclaresNoPluginOnTheContext(): void
    {
        $this->assertNull(
            $this->pluginOn('etc/di.xml'),
            'A plugin on ' . self::INTERCEPTED . ' in the global di.xml binds to nothing.'
        );
    }

    private function pluginOn(string $relativePath): ?string
    {
        $path = dirname(__DIR__, 2) . '/' . $relativePath;

        if (!is_file($path)) {
            return null;
        }

        $config = simplexml_load_file($path);
        $this->assertInstanceOf(SimpleXMLElement::class, $config, $relativePath . ' did not parse.');

        foreach ($config->type as $type) {
            if ((string) $type['name'] !== self::INTERCEPTED) {
                continue;
            }

            foreach ($type->plugin ?? [] as $plugin) {
                return (string) $plugin['type'];
            }
        }

        return null;
    }
}
