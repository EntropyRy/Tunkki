<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class SecurityControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    public function testLoginRedirectsAuthenticatedUserToDashboard(): void
    {
        $member = MemberFactory::new()->english()->create([
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', '/login');

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertSame('/en/dashboard', parse_url($location, \PHP_URL_PATH));
    }

    public function testLoginShowsAuthenticationErrorAndLastUsername(): void
    {
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            new AuthenticationException('Auth failed'),
        );
        $session->set(SecurityRequestAttributes::LAST_USERNAME, 'fail@example.test');
        $session->save();

        $this->client->getCookieJar()->set(new Cookie(
            $session->getName(),
            $session->getId(),
        ));

        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-danger');
        $this->client->assertSelectorExists('input#inputEmail[value="fail@example.test"]');
    }

    public function testLoginSuppressesErrorWhenMattermostFailureFlagPresent(): void
    {
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            new AuthenticationException('Auth failed'),
        );
        $session->set('auth.mm.failure', true);
        $session->save();

        $this->client->getCookieJar()->set(new Cookie(
            $session->getName(),
            $session->getId(),
        ));

        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorNotExists('.alert.alert-danger');
    }
}
