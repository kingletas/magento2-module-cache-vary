<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\CacheVary\Test\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * This module reads Adobe Commerce tables and must still load without Adobe Commerce.
 */
final class OpenSourceCompatibilityTest extends TestCase
{
    /**
     * Namespaces that ship only with Adobe Commerce.
     */
    private const COMMERCE_ONLY = [
        'Magento\\AdminGws',
        'Magento\\Banner',
        'Magento\\CustomerBalance',
        'Magento\\CustomerSegment',
        'Magento\\GiftCardAccount',
        'Magento\\Logging',
        'Magento\\Reward',
        'Magento\\Rma',
        'Magento\\Staging',
        'Magento\\TargetRule',
        'Magento\\VersionsCms',
    ];

    private const SOURCE_DIRECTORIES = ['Api', 'Console', 'Model', 'Plugin'];

    /**
     * Comments are stripped first: naming the class in a docblock is documentation, not a dependency.
     */
    public function testNoExecutableCodeNamesAnAdobeCommerceClass(): void
    {
        $offences = [];

        foreach ($this->sourceFiles() as $file) {
            $code = $this->withoutComments((string) file_get_contents($file));

            foreach (self::COMMERCE_ONLY as $namespace) {
                if (str_contains($code, $namespace)) {
                    $offences[] = sprintf('%s names %s', basename($file), $namespace);
                }
            }
        }

        self::assertSame([], $offences, implode("\n  ", $offences));
    }

    public function testTheManifestRequiresNoAdobeCommercePackage(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $required = array_keys((array) ($manifest['require'] ?? []));

        $commerce = array_filter(
            $required,
            static fn (string $package): bool => in_array($package, [
                'magento/module-customer-segment',
                'magento/module-banner',
                'magento/module-target-rule',
                'magento/product-enterprise-edition',
            ], true)
        );

        self::assertSame([], array_values($commerce));
    }

    /**
     * @return string[]
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (self::SOURCE_DIRECTORIES as $directory) {
            $path = dirname(__DIR__, 2) . '/' . $directory;

            if (!is_dir($path)) {
                continue;
            }

            /** @var \SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        self::assertNotSame([], $files, 'No source files were found to check.');

        return $files;
    }

    private function withoutComments(string $code): string
    {
        $kept = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $kept .= is_array($token) ? $token[1] : $token;
        }

        return $kept;
    }
}
