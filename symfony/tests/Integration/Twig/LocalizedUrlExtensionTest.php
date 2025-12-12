<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Integration test for LocalizedUrlExtension Twig function.
 *
 * Tests the localized_url() Twig function to ensure proper locale URL generation.
 * By testing at the integration level (using Twig Environment), we automatically
 * cover getFunctions() registration without needing to test it explicitly.
 */
final class LocalizedUrlExtensionTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->twig = $container->get(Environment::class);
    }

    public function testLocalizedUrlFunctionExists(): void
    {
        // Verify the function is registered (this also exercises getFunctions())
        $function = $this->twig->getFunction('localized_url');

        self::assertNotNull($function, 'localized_url function should be registered');
        self::assertSame('localized_url', $function->getName());
    }

    public function testLocalizedUrlWithoutRequestReturnsRoot(): void
    {
        // Without request context, should return fallback
        $template = $this->twig->createTemplate('{{ localized_url("en") }}');
        $rendered = $template->render([]);

        // Without request, returns '/'
        self::assertSame('/', $rendered);
    }

    public function testLocalizedUrlFinnishLocale(): void
    {
        $template = $this->twig->createTemplate('{{ localized_url("fi") }}');
        $rendered = $template->render([]);

        // Finnish returns root or Finnish-prefixed URL
        self::assertNotEmpty($rendered, 'Should return a URL');
        self::assertMatchesRegularExpression('#^/#', $rendered, 'Should start with /');
    }

    public function testLocalizedUrlEnglishLocale(): void
    {
        $template = $this->twig->createTemplate('{{ localized_url("en") }}');
        $rendered = $template->render([]);

        // English returns root or English-prefixed URL
        self::assertNotEmpty($rendered, 'Should return a URL');
        self::assertMatchesRegularExpression('#^/#', $rendered, 'Should start with /');
    }
}
