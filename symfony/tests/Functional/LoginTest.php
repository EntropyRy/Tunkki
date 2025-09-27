<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Http\SiteAwareKernelBrowser;
use App\Tests\_Base\FixturesWebTestCase;

require_once __DIR__ . "/../Http/SiteAwareKernelBrowser.php";

final class LoginTest extends FixturesWebTestCase
{
    /**
     * Single SiteAwareKernelBrowser instance for all tests.
     * Provides consistent locale without SiteRequest wrapping.
     */
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        putenv("TEST_DEBUG_LOGIN=1");
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
    }

    public function testFixtureUserCanLogin(): void
    {
        $client = $this->client;

        $crawler = $client->request("GET", "/login");
        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            "Login page should load for normal user.",
        );

        $formNode = $crawler->filter("form")->first();
        $this->assertTrue(
            $formNode->count() > 0,
            "Expected a login form on /login page.",
        );

        $form = $formNode->form([
            "_username" => "testuser@example.com",
            "_password" => "userpass123",
        ]);
        $client->submit($form);

        $container = static::getContainer();
        $tokenStorage = $container->get("security.token_storage");
        $requestStack = $container->get("request_stack");
        $token = $tokenStorage->getToken();
        $status = $client->getResponse()->getStatusCode();
        $loc = $client->getResponse()->headers->get("Location") ?? "";
        $currentPath = $requestStack->getCurrentRequest()
            ? $requestStack->getCurrentRequest()->getPathInfo()
            : "N/A";

        fwrite(
            \STDOUT,
            "[LOGIN DEBUG USER RAW] after submit status={$status} location='{$loc}' path='{$currentPath}' token=" .
                ($token
                    ? get_class($token) .
                        " roles=" .
                        implode(",", $token->getRoleNames())
                    : "NULL") .
                "\n",
        );

        for ($i = 0; $i < 5; $i++) {
            $status = $client->getResponse()->getStatusCode();
            if ($status !== 302) {
                break;
            }
            $loc = $client->getResponse()->headers->get("Location") ?? "";
            if ($loc === "" || $loc === "/") {
                break;
            }
            // Normalize absolute URLs (strip scheme/host for local redirects)
            if (preg_match("#^https?://#i", $loc)) {
                $parts = parse_url($loc);
                $loc = $parts["path"] ?? "/";
                if (!empty($parts["query"])) {
                    $loc .= "?" . $parts["query"];
                }
            }
            $loc = preg_replace("#^//+#", "/", $loc);
            if (!str_starts_with($loc, "/")) {
                $loc = "/" . ltrim($loc, "/");
            }
            if (str_contains($loc, "/login") && $i > 0) {
                $snippet = substr(
                    $client->getResponse()->getContent() ?? "",
                    0,
                    400,
                );
                $this->fail(
                    "Redirect loop back to /login. Snippet:\n{$snippet}",
                );
            }
            fwrite(
                \STDOUT,
                "[LOGIN DEBUG USER RAW] redirect hop {$i} -> {$loc}\n",
            );
            $client->request("GET", $loc);
        }

        if ($client->getResponse()->getStatusCode() === 404) {
            $client->request("GET", "/en/dashboard");
        }
        if ($client->getResponse()->getStatusCode() === 404) {
            $client->request("GET", "/dashboard");
        }
        if ($client->getResponse()->getStatusCode() === 404) {
            $client->request("GET", "/yleiskatsaus");
        }

        $token = $tokenStorage->getToken();
        $finalPath = $requestStack->getCurrentRequest()
            ? $requestStack->getCurrentRequest()->getPathInfo()
            : "N/A";
        fwrite(
            \STDOUT,
            "[LOGIN DEBUG USER RAW] final status=" .
                $client->getResponse()->getStatusCode() .
                " path='{$finalPath}' token=" .
                ($token
                    ? get_class($token) .
                        " roles=" .
                        implode(",", $token->getRoleNames())
                    : "NULL") .
                "\n",
        );

        $this->assertNotNull(
            $token,
            "Expected an authenticated security token; got NULL (raw browser).",
        );
        $content = $client->getResponse()->getContent() ?? "";
        $this->assertTrue(
            str_contains(strtolower($content), "test"),
            "Expected 'Test' in response content (len=" .
                strlen($content) .
                ").",
        );
    }

    // siteAware helper removed; unified LocaleAwareKernelBrowser used for all tests.

    public function testAdminUserCanAccessAdmin(): void
    {
        $client = $this->client;

        $repo = self::$em->getRepository(User::class);
        $admin = null;
        foreach ($repo->findAll() as $u) {
            if (
                $u->getMember() &&
                $u->getMember()->getEmail() === "admin@example.com"
            ) {
                $admin = $u;
                break;
            }
        }
        $this->assertNotNull($admin, "Admin fixture user not found.");
        $client->loginUser($admin);

        $client->request("GET", "/admin/dashboard");
        $status = $client->getResponse()->getStatusCode();
        if ($status === 404) {
            $client->request("GET", "/admin/");
            $status = $client->getResponse()->getStatusCode();
            if ($status === 302) {
                $loc = $client->getResponse()->headers->get("Location") ?? "";
                if ($loc) {
                    $client->request("GET", $loc);
                    $status = $client->getResponse()->getStatusCode();
                }
            }
        }

        $this->assertSame(
            200,
            $status,
            "Admin should reach admin dashboard (site-aware client).",
        );
    }

    public function testSuperAdminCanAccessAdminDashboard(): void
    {
        $client = $this->client;

        $repo = self::$em->getRepository(User::class);
        $super = null;
        foreach ($repo->findAll() as $u) {
            if (
                $u->getMember() &&
                $u->getMember()->getEmail() === "superadmin@example.com"
            ) {
                $super = $u;
                break;
            }
        }
        $this->assertNotNull($super, "Super admin fixture user not found.");
        $client->loginUser($super);

        $client->request("GET", "/admin/dashboard");
        $status = $client->getResponse()->getStatusCode();
        if ($status === 404) {
            $client->request("GET", "/admin/");
            $status = $client->getResponse()->getStatusCode();
            if ($status === 302) {
                $loc = $client->getResponse()->headers->get("Location") ?? "";
                if ($loc) {
                    $client->request("GET", $loc);
                    $status = $client->getResponse()->getStatusCode();
                }
            }
        }

        $this->assertSame(
            200,
            $status,
            "Super admin should reach admin dashboard (site-aware client).",
        );
    }
}
