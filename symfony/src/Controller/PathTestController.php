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
        name: 'path_test'
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

        // Cross-locale generation summary
        $data = [
            'current_locale' => $currentLocale,
            'current_site_relative_path' => $currentSite?->getRelativePath(),
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
                'fi.relative_should_be' => '/path-testi',
                // Expect English relative URL (always begins with a leading slash):
                //   - Finnish site: /en/path-test
                //   - English site: /path-test
                'en.relative_should_start_with' => 'en' === $currentLocale ? '/path-test' : '/en',
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
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $rows = [];
        foreach (['fi', 'en'] as $loc) {
            foreach (['relative', 'absolute', 'network'] as $kind) {
                $url = $data['routes'][$loc][$kind];
                $rows[] = sprintf(
                    '<tr><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
                    $esc($loc),
                    $esc($kind),
                    $esc($url)
                );
            }
        }

        $expectRows = [];
        foreach ($data['expectations'] as $k => $v) {
            $expectRows[] = sprintf(
                '<tr><td>%s</td><td><code>%s</code></td></tr>',
                $esc($k),
                $esc(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)
            );
        }

        $rowsHtml = implode('', $rows);
        $expectHtml = implode('', $expectRows);

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
  </style>
</head>
<body>
  <h1>Path Test Controller</h1>
  <p>Current locale: <strong>{$esc($data['current_locale'])}</strong></p>
  <p>Current site relativePath: <strong>{$esc((string) ($data['current_site_relative_path'] ?? ''))}</strong></p>

  <table>
    <caption>Generated URLs</caption>
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
