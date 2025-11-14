<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Tests\_Base\FixturesWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Zenstruck\Foundry\Test\Factories;

abstract class LiveComponentTestCase extends FixturesWebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure BrowserKit assertions/tests have a crawler primed for both locales.
        $this->seedClientHome('fi');
        $this->seedClientHome('en');
        $this->primeRequestStackSession();
    }

    /**
     * Convenience helper to create a live component wired to the site-aware client.
     */
    protected function mountComponent(string $componentName, array $data = [], string $locale = 'fi'): TestLiveComponent
    {
        $this->primeRequestStackSession();
        $component = $this->createLiveComponent($componentName, $data, $this->client());

        if (null !== $locale) {
            $component->setRouteLocale($locale);
        }

        return $component;
    }

    private function primeRequestStackSession(): void
    {
        $container = self::getContainer();
        if (!$container->has('request_stack')) {
            return;
        }

        $session = null;
        if ($container->has('session')) {
            $session = $container->get('session');
        } elseif ($container->has('session.factory')) {
            $session = $container->get('session.factory')->createSession();
        }

        if ($session instanceof SessionInterface && !$session->isStarted()) {
            $session->start();
        }

        $request = Request::create('/');
        if ($request->hasSession()) {
            $request->getSession()->start();
        } elseif ($session instanceof SessionInterface) {
            $request->setSession($session);
        }

        $container->get('request_stack')->push($request);
    }
}
