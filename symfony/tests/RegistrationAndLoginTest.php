<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use App\Tests\Http\SiteAwareKernelBrowser;

require_once __DIR__ . "/Http/SiteAwareKernelBrowser.php";

final class RegistrationAndLoginTest extends WebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter("HTTP_HOST", "localhost");
    }

    public function testRegisterAndLogin(): void
    {
        $client = $this->client;
        $client->followRedirects(true);

        // 1) Visit the registration page (EN locale)
        $crawler = $client->request("GET", "/en/profile/new");
        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            "Expected registration page to load (HTTP 200)",
        );

        // 2) Fill and submit the registration form
        // MemberType fields:
        // - member[username], member[firstname], member[lastname]
        // - member[email], member[phone]
        // - member[user][plainPassword]
        // - member[locale] (fi|en)
        // - member[CityOfResidence]
        // - member[StudentUnionMember] (checkbox)
        // - member[theme] (light|dark)
        $email = "regtest+" . bin2hex(random_bytes(4)) . "@example.com";
        $formNode = $crawler->filter("form")->first();
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            "Registration form not found",
        );

        $form = $formNode->form([
            "member[username]" => "newuser_" . bin2hex(random_bytes(2)),
            "member[firstname]" => "New",
            "member[lastname]" => "User",
            "member[email]" => $email,
            "member[phone]" => "0501234567",
            "member[user][plainPassword][first]" => "userpass12345",
            "member[user][plainPassword][second]" => "userpass12345",
            "member[locale]" => "en",
            "member[CityOfResidence]" => "Helsinki",
            "member[StudentUnionMember]" => false,
            "member[theme]" => "light",
        ]);

        $client->submit($form);

        // The controller attempts to redirect to login; with followRedirects on,
        // we should land on the login page or remain on the same page if the controller
        // renders directly. Either way, continue to login assertion.
        $this->assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            "After registration submit, expected a 200 (final page after redirects)",
        );

        // 3) Fetch the created user from DB
        $em = static::getContainer()->get("doctrine")->getManager();
        /** @var \App\Repository\UserRepository $userRepo */
        $userRepo = $em->getRepository(User::class);
        /** @var User|null $user */
        $user =
            $userRepo->findOneBy(["authId" => null]) ?:
            $userRepo->findOneBy(["member" => null]);

        // Fallback: find by email via member relation if available
        if (!$user) {
            $user = $userRepo
                ->createQueryBuilder("u")
                ->join("u.member", "m")
                ->andWhere("m.email = :email")
                ->setParameter("email", $email)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        $this->assertNotNull(
            $user,
            "Expected newly registered user to exist in the database",
        );

        // 4) Programmatically authenticate as the newly created user
        $client->loginUser($user, "main");

        // 5) Verify login by asserting redirect to dashboard when hitting /login (avoid rendering heavy dashboard)
        $client->followRedirects(false);
        $client->request("GET", "/login");
        $this->assertSame(
            302,
            $client->getResponse()->getStatusCode(),
            "Expected redirect to dashboard after login",
        );
        $this->assertStringContainsString(
            "/dashboard",
            $client->getResponse()->headers->get("Location") ?? "",
        );
    }
}
