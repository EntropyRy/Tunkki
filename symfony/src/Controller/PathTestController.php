<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\PageBundle\Site\SiteSelectorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller to exercise the Symfony localized route generation across locales
 * with automatic site-locale selection and explicit _locale forcing.
 *
 * NOTE:
 *  Add "path_test" to sonata_page.ignore_routes in config/packages/sonata_page.yaml
 *  so Sonata PageBundle does not attempt to create a hybrid CMS Page for it:
 *
 *    sonata_page:
 *      ignore_routes:
 *        - path_test
 *        # (other existing ignored routes...)
 *
 *  This route relies on Symfony resource-level locale prefixing (fi:"", en:"/en")
 *  and defines the English localized path WITHOUT embedding /en so that:
 *   - Generation via base route name + _locale never double-prefixes.
 *   - Cross-locale generation from the Finnish site yields /en/path-test.
 *
 *  Finnish variant (effective): /path-testi
 *  English variant (effective): /en/path-test
 */
final class PathTestController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/path-testi',
            // English variant intentionally omits /en because the site relativePath
            // (and the locale-aware router decorator) will supply the prefix when appropriate.
            'en' => '/path-test',
        ],
        name: 'path_test',
        env: 'dev',
    )]
    public function index(
        Request $request,
        UrlGeneratorInterface $router,
        SiteSelectorInterface $siteSelector,
    ): Response {
        $currentSite = $siteSelector->retrieve();
        $currentLocale = $request->attributes->get('_locale') ?? $currentSite?->getLocale() ?? 'n/a';

        // Automatic site-locale generation (no _locale param) and forced cross-locale variants
        $autoRelative = $router->generate('path_test', [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $autoAbsolute = $router->generate('path_test', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $autoNetwork = $router->generate('path_test', [], UrlGeneratorInterface::NETWORK_PATH);

        // Forced variants (explicit locale override)
        // Use ABSOLUTE_PATH so "relative" values always include a leading slash.
        $fiRelative = $router->generate('path_test', ['_locale' => 'fi'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $fiAbsolute = $router->generate('path_test', ['_locale' => 'fi'], UrlGeneratorInterface::ABSOLUTE_URL);
        $fiNetwork = $router->generate('path_test', ['_locale' => 'fi'], UrlGeneratorInterface::NETWORK_PATH);

        $enRelative = $router->generate('path_test', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $enAbsolute = $router->generate('path_test', ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL);
        $enNetwork = $router->generate('path_test', ['_locale' => 'en'], UrlGeneratorInterface::NETWORK_PATH);

        // Validate automatic route generation matches current locale
        // Note: In multisite setup, English site has relativePath="/en", so the generated URL
        // includes the prefix. Finnish has relativePath=null, so no prefix.
        $expectedAutoPath = 'en' === $currentLocale ? '/en/path-test' : '/path-testi';
        $autoMatchesCurrentLocale = $autoRelative === $expectedAutoPath;

        // Cross-locale generation summary
        $data = [
            'current_locale' => $currentLocale,
            'current_site_relative_path' => $currentSite?->getRelativePath(),
            'client_ip' => [
                'client_ip' => $request->getClientIp(),
                'server_remote_addr' => $request->server->get('REMOTE_ADDR'),
                'x_forwarded_for' => $request->server->get('HTTP_X_FORWARDED_FOR'),
                'x_real_ip' => $request->server->get('HTTP_X_REAL_IP'),
                'cf_connecting_ip' => $request->server->get('HTTP_CF_CONNECTING_IP'),
            ],
            'routes' => [
                'auto' => [
                    'relative' => $autoRelative,
                    'absolute' => $autoAbsolute,
                    'network' => $autoNetwork,
                    'locale' => $currentLocale,
                ],
                'forced' => [
                    'fi' => [
                        'relative' => $fiRelative,
                        'absolute' => $fiAbsolute,
                        'network' => $fiNetwork,
                    ],
                    'en' => [
                        'relative' => $enRelative,
                        'absolute' => $enAbsolute,
                        'network' => $enNetwork,
                    ],
                ],
            ],
            'expectations' => [
                'auto.matches_current_locale' => $autoMatchesCurrentLocale,
                'auto.expected_path' => $expectedAutoPath,
                'auto.actual_path' => $autoRelative,
                'fi.relative_should_be' => '/path-testi',
                // Expect English relative URL (always begins with a leading slash):
                //   - Finnish site forcing en: /en/path-test
                //   - English site: /en/path-test (includes site relativePath)
                'en.relative_should_be' => '/en/path-test',
                'en.matches_expected' => '/en/path-test' === $enRelative,
                'no_double_en_prefix' => !str_contains($enRelative, '/en/en/'),
            ],
        ];

        if ($request->query->getBoolean('json', false) || 'json' === $request->getRequestFormat()) {
            return new JsonResponse($data);
        }

        // Simple inline HTML (kept here to avoid requiring a new template file).
        $html = $this->renderHtml($data);

        return new Response($html);
    }

    private function renderHtml(array $data): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        // Auto-generation rows (no _locale parameter - uses context locale)
        $autoRows = [];
        foreach (['relative', 'absolute', 'network'] as $kind) {
            $url = $data['routes']['auto'][$kind];
            $autoRows[] = \sprintf(
                '<tr><td>%s</td><td><code>%s</code></td></tr>',
                $esc($kind),
                $esc($url)
            );
        }

        $rows = [];
        foreach (['fi', 'en'] as $loc) {
            foreach (['relative', 'absolute', 'network'] as $kind) {
                $url = $data['routes']['forced'][$loc][$kind];
                $rows[] = \sprintf(
                    '<tr><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
                    $esc($loc),
                    $esc($kind),
                    $esc($url)
                );
            }
        }

        $expectRows = [];
        foreach ($data['expectations'] as $k => $v) {
            $display = \is_bool($v) ? ($v ? '✅ true' : '❌ false') : (string) $v;
            $style = '';
            if (\is_bool($v)) {
                $style = $v ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;';
            }
            $expectRows[] = \sprintf(
                '<tr style="%s"><td>%s</td><td><code>%s</code></td></tr>',
                $style,
                $esc($k),
                $esc($display)
            );
        }

        $autoRowsHtml = implode('', $autoRows);
        $rowsHtml = implode('', $rows);
        $expectHtml = implode('', $expectRows);

        $ipRows = [];
        foreach ($data['client_ip'] as $k => $v) {
            $ipRows[] = \sprintf(
                '<tr><td>%s</td><td><code>%s</code></td></tr>',
                $esc($k),
                $esc((string) ($v ?? 'null'))
            );
        }
        $ipHtml = implode('', $ipRows);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$esc($data['current_locale'])}">
<head>
  <meta charset="utf-8">
  <title>Path Test Controller</title>
  <style>
    body { font-family: system-ui, Arial, sans-serif; margin: 2rem; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 2rem; }
    th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
    th { background: #f5f5f5; }
    code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
    caption { text-align: left; font-weight: bold; padding: .25rem 0 .5rem; }
    .client-ip { background: #e8f4f8; padding: 1rem; border-radius: 5px; margin-bottom: 2rem; }
  </style>
</head>
<body>
  <h1>Path Test Controller</h1>
  <p>Current locale: <strong>{$esc($data['current_locale'])}</strong></p>
  <p>Current site relativePath: <strong>{$esc((string) ($data['current_site_relative_path'] ?? ''))}</strong></p>

  <div class="client-ip">
    <h2>Client IP Information</h2>
    <table>
      <thead>
        <tr><th>Source</th><th>Value</th></tr>
      </thead>
      <tbody>
        {$ipHtml}
      </tbody>
    </table>
  </div>

  <table style="background: #fff3cd; border: 2px solid #ffc107;">
    <caption style="color: #856404;">🔍 Automatic Route Generation (no _locale parameter)</caption>
    <thead>
      <tr><th>Type</th><th>Generated URL</th></tr>
    </thead>
    <tbody>
      {$autoRowsHtml}
    </tbody>
  </table>
  <p style="background: #d1ecf1; padding: 1rem; border-radius: 5px; border-left: 4px solid #0c5460;">
    <strong>Key Test:</strong> The URLs above are generated WITHOUT setting <code>_locale</code>.
    They should automatically use the current site's locale (<strong>{$esc($data['current_locale'])}</strong>).
    <br>
    Expected:
    <code>{$esc('en' === $data['current_locale'] ? '/en/path-test' : '/path-testi')}</code>
    <br>
    <small>(English includes /en prefix from site relativePath)</small>
  </p>

  <table>
    <caption>Forced Locale URLs (with explicit _locale parameter)</caption>
    <thead>
      <tr><th>Route locale</th><th>Type</th><th>URL</th></tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <table>
    <caption>Expectations / Checks</caption>
    <thead>
      <tr><th>Key</th><th>Value / Status</th></tr>
    </thead>
    <tbody>
      {$expectHtml}
    </tbody>
  </table>

  <p>JSON version: append <code>?json=1</code></p>
</body>
</html>
HTML;
    }
}
