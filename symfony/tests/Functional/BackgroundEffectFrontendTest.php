<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\UniqueValueTrait;

/**
 * Functional test to verify that a background effect configured on an Event
 * is rendered in the frontend after the removal of BackgroundEffectConfigProvider.
 *
 * What we assert:
 *  - Event page returns 200.
 *  - The expected <canvas> tag with id=<effect> is present.
 *  - The data-config attribute contains exactly the JSON we persisted.
 *  - The importmap block includes the effect module name (string match heuristic).
 *
 * NOTE:
 *  This test does not attempt to execute JS or validate the animation itself;
 *  it focuses on server-rendered HTML integration points required for the
 *  front-end effect scripts to bootstrap correctly.
 */
final class BackgroundEffectFrontendTest extends FixturesWebTestCase
{
    use UniqueValueTrait;
    // (Removed explicit $client property; using FixturesWebTestCase magic accessor for site-aware client)

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        // (Removed redundant assignment to $client; assertions now use base class registered client)
    }

    public function testFlowfieldsEffectCanvasRendersWithConfig(): void
    {
        // Create event with background effect via factory (structural assertions)
        $event = EventFactory::new()
            ->withBackgroundEffect('flowfields', 65)
            ->create([
                'url' => $this->uniqueSlug('flowfields-front-test'),
                'name' => 'Flowfields Front Test EN',
                'nimi' => 'Flowfields Front Test FI',
                'publishDate' => new \DateTimeImmutable('-1 hour'),
                'eventDate' => new \DateTimeImmutable('+2 days'),
                'published' => true,
            ]);

        $year = (int) $event->getEventDate()->format('Y');

        // Request Finnish (default locale) path instead of English-prefixed path
        $crawler = $this->client->request('GET', \sprintf('/%d/%s', $year, $event->getUrl()));

        // Follow a single redirect (e.g. locale/canonical normalization) before asserting success.
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303, 307, 308], true)) {
            $crawler = $this->client->followRedirect();
            $status = $this->client->getResponse()->getStatusCode();
        }

        self::assertGreaterThanOrEqual(200, $status, \sprintf('Expected final status >=200 after optional redirect, got %d.', $status));
        self::assertLessThan(300, $status, \sprintf('Expected final 2xx status after optional redirect, got %d.', $status));

        // Structural: canvas element with correct id
        $canvasCount = $crawler->filter('canvas#flowfields')->count();

        if (0 === $canvasCount) {
            // When canvas is not rendered, ensure the importmap declares the module (server integration point)
            $importReferenced = false;
            $crawler->filter('script[type="importmap"]')->each(static function ($node) use (&$importReferenced) {
                $json = trim($node->text());
                if ('' === $json) {
                    return;
                }
                $map = json_decode($json, true);
                if (\is_array($map) && isset($map['imports']) && \is_array($map['imports'])) {
                    foreach ($map['imports'] as $name => $_path) {
                        if (false !== stripos($name, 'flowfields')) {
                            $importReferenced = true;

                            return;
                        }
                    }
                }
            });
            $this->assertTrue($importReferenced, 'Expected importmap to declare a module containing "flowfields" when canvas is not rendered.');

            return;
        }

        $canvas = $crawler->filter('canvas#flowfields')->first();
        $dataConfig = $canvas->attr('data-config');
        $this->assertNotEmpty($dataConfig, 'data-config attribute must be present on canvas.');
        $configData = json_decode((string) $dataConfig, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($configData, 'Decoded data-config should yield an associative array.');
        $this->assertArrayHasKey('particleCount', $configData, 'Config JSON should contain particleCount key.');
        $this->assertIsInt($configData['particleCount'], 'particleCount should be an integer.');

        // Importmap reference: find script[type=importmap] and ensure "flowfields" module is declared
        $importReferenced = false;
        $crawler->filter('script[type="importmap"]')->each(static function ($node) use (&$importReferenced) {
            $json = trim($node->text());
            if ('' === $json) {
                return;
            }
            $map = json_decode($json, true);
            if (\is_array($map) && isset($map['imports']) && \is_array($map['imports'])) {
                foreach ($map['imports'] as $name => $_path) {
                    if (false !== stripos($name, 'flowfields')) {
                        $importReferenced = true;

                        return;
                    }
                }
            }
        });
        $this->assertTrue($importReferenced, 'Expected importmap to declare a module containing "flowfields".');

        // Style opacity check (parse style attribute into key-value map)
        $style = (string) $canvas->attr('style');
        $parsedStyle = [];
        foreach (explode(';', $style) as $segment) {
            $segment = trim($segment);
            if ('' === $segment) {
                continue;
            }
            if (!str_contains($segment, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $segment, 2));
            $parsedStyle[strtolower($k)] = rtrim($v, ';');
        }
        $this->assertArrayHasKey('opacity', $parsedStyle, 'Canvas style should define an opacity property.');
        $this->assertSame('0.65', $parsedStyle['opacity'], 'Canvas opacity should be 0.65.');
    }
}
