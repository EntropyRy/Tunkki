<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LocalizedUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('localized_url', $this->getLocalizedUrl(...)),
            new TwigFunction('localized_route', $this->getLocalizedRoute(...)),
        ];
    }

    public function getLocalizedUrl(string $targetLocale): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return '/';
        }

        $currentPath = $request->getPathInfo();
        $currentRoute = $request->attributes->get('_route');

        // Handle root path
        if ($currentPath === '/' || $currentPath === '/en') {
            return $targetLocale === 'en' ? '/en' : '/';
        }

        // If we have a route, try to get its localized version
        if ($currentRoute) {
            // Strip locale suffix if present
            $baseRoute = preg_replace('/\.(en|fi)$/', '', (string) $currentRoute);
            $targetRoute = $baseRoute . '.' . $targetLocale;

            // Get route parameters from the current request
            $routeParams = $request->attributes->get('_route_params', []);

            try {
                // Generate URL with the current parameters
                $url = $this->router->generate($targetRoute, $routeParams);

                // For English locale, ensure /en prefix
                if ($targetLocale === 'en' && !str_starts_with($url, '/en')) {
                    return '/en' . $url;
                }

                // For Finnish locale, remove /en prefix if present
                if ($targetLocale === 'fi' && str_starts_with($url, '/en')) {
                    return substr($url, 3);
                }

                return $url;
            } catch (\Throwable) {
                // Fallback if route generation fails
                $pathWithoutPrefix = str_starts_with($currentPath, '/en') ? substr($currentPath, 3) : $currentPath;
                return $targetLocale === 'en' ? '/en' . $pathWithoutPrefix : $pathWithoutPrefix;
            }
        }

        // Fallback to default handling
        $pathWithoutPrefix = str_starts_with($currentPath, '/en') ? substr($currentPath, 3) : $currentPath;
        return $targetLocale === 'en' ? '/en' . $pathWithoutPrefix : $pathWithoutPrefix;
    }

    /**
     * Generate a localized URL based on a specified route and parameters.
     *
     * @param string $route The route name without locale suffix
     * @param string $targetLocale The target locale (e.g., 'en', 'fi')
     * @param array $parameters The route parameters
     * @return string The generated URL
     */
    public function getLocalizedRoute(string $route, string $targetLocale, array $parameters = []): string
    {
        // Strip any existing locale suffix if present
        $baseRoute = preg_replace('/\.(en|fi)$/', '', $route);
        $targetRoute = $baseRoute . '.' . $targetLocale;

        try {
            // Generate URL with the provided parameters
            $url = $this->router->generate($targetRoute, $parameters);

            // For English locale, ensure /en prefix
            if ($targetLocale === 'en' && !str_starts_with($url, '/en')) {
                return '/en' . $url;
            }

            // For Finnish locale, remove /en prefix if present
            if ($targetLocale === 'fi' && str_starts_with($url, '/en')) {
                return substr($url, 3);
            }

            return $url;
        } catch (\Throwable) {
            // Fallback to root URL if route generation fails
            return $targetLocale === 'en' ? '/en' : '/';
        }
    }
}
