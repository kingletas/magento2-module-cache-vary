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
 * What this module does to a store that installs it and changes nothing.
 */
class ShippedDefaultsTest extends TestCase
{
    /**
     * The one that matters: installing must not silently move every cache key.
     */
    public function testThePolicyIsOffOutOfTheBox(): void
    {
        $this->assertSame('0', $this->default('policy/enabled'));
    }

    public function testNoSegmentIsAllowlistedOutOfTheBox(): void
    {
        $this->assertSame('', $this->default('policy/cacheable_customer_segments'));
    }

    public function testTheBudgetHasAShippedValue(): void
    {
        $this->assertSame('8', $this->default('policy/bucket_budget'));
    }

    /**
     * The section id is what every config path hangs off, and `bin/rebrand` rewrites it.
     */
    public function testTheSectionIdMatchesTheOneDiXmlConfigures(): void
    {
        $section = $this->config()->default->children()[0]->getName();

        $di = simplexml_load_file(dirname(__DIR__, 2) . '/etc/di.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $di, 'etc/di.xml did not parse.');

        $configured = null;

        foreach ($di->type as $type) {
            foreach ($type->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === 'section') {
                    $configured = trim((string) $argument);
                }
            }
        }

        $this->assertNotNull($configured, 'No <argument name="section"> found in etc/di.xml.');
        $this->assertSame($configured, $section);
    }

    /**
     * A rule reading a path with no default would report a ceiling nobody configured.
     */
    public function testEveryRuleConfigPathHasADefault(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 2) . '/etc/di.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $di, 'etc/di.xml did not parse.');

        $paths = [];

        foreach ($di->virtualType as $virtualType) {
            foreach ($virtualType->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === 'configPath') {
                    $paths[] = trim((string) $argument);
                }
            }
        }

        $this->assertNotSame([], $paths, 'No rule declares a configPath.');

        foreach ($paths as $path) {
            $this->assertSame('', $this->default($path), $path . ' should ship empty.');
        }
    }

    /**
     * The guard is what keeps the policy off caching applications it is not verified against.
     */
    public function testOnlyVarnishIsAcceptedOutOfTheBox(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 2) . '/etc/di.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $di, 'etc/di.xml did not parse.');

        $accepted = null;

        foreach ($di->type as $type) {
            if (!str_ends_with((string) $type['name'], '\\PolicyGuard')) {
                continue;
            }

            foreach ($type->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === 'cachingApplications') {
                    $accepted = array_map('trim', array_map('strval', iterator_to_array($argument->item, false)));
                }
            }
        }

        $this->assertNotNull($accepted, 'PolicyGuard declares no cachingApplications in etc/di.xml.');
        $this->assertSame(['2'], $accepted, 'Only Varnish (2) should ship accepted.');
    }

    /**
     * The coverage check probes rules by key, so a mismatch here would silently check nothing.
     */
    public function testTheSegmentSourceAndTheRuleNameTheSameContextKey(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 2) . '/etc/di.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $di, 'etc/di.xml did not parse.');

        $ruleKey = null;

        foreach ($di->virtualType as $virtualType) {
            foreach ($virtualType->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === 'key') {
                    $ruleKey = trim((string) $argument);
                }
            }
        }

        $sourceKey = null;

        foreach ($di->type as $type) {
            foreach ($type->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === 'contextKey') {
                    $sourceKey = trim((string) $argument);
                }
            }
        }

        $this->assertNotNull($ruleKey, 'No rule declares a key in etc/di.xml.');
        $this->assertSame($ruleKey, $sourceKey, 'The segment source must report on the key the rule governs.');
    }

    /**
     * The keys that say who the shopper is, which no rule may collapse.
     */
    public function testTheIdentityKeysShipProtected(): void
    {
        $this->assertSame(
            ['customer_group', 'customer_logged_in'],
            $this->arrayArgument('protectedKeys')
        );
    }

    /**
     * And the shipped rules govern none of them.
     */
    public function testNoShippedRuleGovernsAProtectedKey(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 2) . '/etc/di.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $di, 'etc/di.xml did not parse.');

        $governed = [];

        foreach ($di->virtualType as $virtualType) {
            foreach ($virtualType->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === 'key') {
                    $governed[] = trim((string) $argument);
                }
            }
        }

        $this->assertSame([], array_intersect($governed, $this->arrayArgument('protectedKeys')));
    }

    /**
     * @return string[]
     */
    private function arrayArgument(string $name): array
    {
        $di = simplexml_load_file(dirname(__DIR__, 2) . '/etc/di.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $di, 'etc/di.xml did not parse.');

        foreach ($di->type as $type) {
            foreach ($type->arguments->argument ?? [] as $argument) {
                if ((string) $argument['name'] === $name) {
                    return array_map('trim', array_map('strval', iterator_to_array($argument->item, false)));
                }
            }
        }

        $this->fail(sprintf('No <argument name="%s"> found in etc/di.xml.', $name));
    }

    private function default(string $path): string
    {
        [$group, $field] = explode('/', $path);
        $section = $this->config()->default->children()[0];

        $this->assertTrue(isset($section->{$group}->{$field}), sprintf('%s is not in etc/config.xml.', $path));

        return trim((string) $section->{$group}->{$field});
    }

    private function config(): SimpleXMLElement
    {
        $config = simplexml_load_file(dirname(__DIR__, 2) . '/etc/config.xml');

        $this->assertInstanceOf(SimpleXMLElement::class, $config, 'etc/config.xml did not parse.');

        return $config;
    }
}
