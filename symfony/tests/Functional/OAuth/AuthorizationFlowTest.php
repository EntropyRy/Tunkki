<?php

declare(strict_types=1);

namespace App\Tests\Functional\OAuth;

use App\EventSubscriber\AuthorizationCodeSubscriber;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use App\Tests\Support\OAuthTestHelper;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\Model\ClientInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AuthorizationFlowTest extends FixturesWebTestCase
{
    use LoginHelperTrait;
    use OAuthTestHelper;

    private const WIKI_CLIENT_ID = 'wiki_client_test';
    private const FORUM_CLIENT_ID = 'forum_client_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClientHome('fi');
        $this->createOAuthClient(self::WIKI_CLIENT_ID, ['https://wiki.example.test/callback'], ['authorization_code'], ['wiki', 'forum']);
        $this->createOAuthClient(self::FORUM_CLIENT_ID, ['https://forum.example.test/callback'], ['authorization_code'], ['forum']);
    }

    public function testActiveMemberCanAuthorizeWikiClient(): void
    {
        $email = 'wiki-active-'.bin2hex(random_bytes(4)).'@example.test';
        $this->loginAsActiveMember($email);

        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => self::WIKI_CLIENT_ID,
            'redirect_uri' => 'https://wiki.example.test/callback',
            'response_type' => 'code',
            'scope' => 'wiki',
            'state' => 'wiki_state',
        ]);

        $this->assertResponseRedirects();
        $location = $this->client()->getResponse()->headers->get('Location');
        $this->assertNotFalse($location);
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        self::assertSame('https', $parts['scheme'] ?? null);
        self::assertSame('wiki.example.test', $parts['host'] ?? null);
        self::assertSame('/callback', $parts['path'] ?? null);
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        self::assertNotEmpty($query['code'] ?? null);
        self::assertSame('wiki_state', $query['state'] ?? null);
    }

    public function testNonActiveMemberCannotAuthorizeWikiClient(): void
    {
        $email = 'nonactive-wiki-'.bin2hex(random_bytes(4)).'@example.test';
        MemberFactory::new()
            ->inactive()
            ->finnish()
            ->create(['email' => $email]);

        [$user] = $this->loginAsMember($email);
        $member = $user->getMember();

        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => self::WIKI_CLIENT_ID,
            'redirect_uri' => 'https://wiki.example.test/callback',
            'response_type' => 'code',
            'scope' => 'wiki',
        ]);

        $this->assertResponseRedirects();

        $location = $this->client()->getResponse()->headers->get('Location');
        $this->assertNotFalse($location);
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        self::assertSame('/profiili', $parts['path'] ?? null);

        $this->client()->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client()->assertSelectorExists('.alert-warning');

        self::assertFalse($member->getIsActiveMember());
    }

    public function testNonActiveMemberRedirectsToEnglishProfileIfEnglishLocale(): void
    {
        $member = MemberFactory::new()->inactive()->english()->create();
        $user = $member->getUser();
        $this->client()->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => self::WIKI_CLIENT_ID,
            'redirect_uri' => 'https://wiki.example.test/callback',
            'response_type' => 'code',
            'scope' => 'wiki',
        ]);

        $this->assertResponseRedirects();
        $location = $this->client()->getResponse()->headers->get('Location');
        $this->assertNotFalse($location);
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        self::assertSame('/en/profile', $parts['path'] ?? null);
    }

    public function testNonActiveMemberCanAuthorizeForumClient(): void
    {
        $email = 'forum-nonactive-'.bin2hex(random_bytes(4)).'@example.test';
        $this->loginAsMember($email);

        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => self::FORUM_CLIENT_ID,
            'redirect_uri' => 'https://forum.example.test/callback',
            'response_type' => 'code',
            'scope' => 'forum',
            'state' => 'forum_state',
        ]);

        $this->assertResponseRedirects();
        $location = $this->client()->getResponse()->headers->get('Location');
        $this->assertNotFalse($location);
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        self::assertSame('https', $parts['scheme'] ?? null);
        self::assertSame('forum.example.test', $parts['host'] ?? null);
        self::assertSame('/callback', $parts['path'] ?? null);
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        self::assertSame('forum_state', $query['state'] ?? null);
    }

    public function testAnonymousUserMustLoginForAuthorization(): void
    {
        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => self::WIKI_CLIENT_ID,
            'redirect_uri' => 'https://wiki.example.test/callback',
            'response_type' => 'code',
            'scope' => 'wiki',
        ]);

        $this->assertResponseRedirects();

        $location = $this->client()->getResponse()->headers->get('Location');
        $this->assertNotFalse($location);
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        self::assertSame('/login', $parts['path'] ?? null);
    }

    public function testAuthorizationSubscriberRedirectsNonUserToLoginWithReturnUrl(): void
    {
        $request = Request::create('http://localhost/oauth/authorize?client_id=wiki_client_test');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $subscriber = new AuthorizationCodeSubscriber(
            static::getContainer()->get(UrlGeneratorInterface::class),
            $requestStack,
        );

        $authRequest = $this->createStub(AuthorizationRequestInterface::class);
        $client = $this->createStub(ClientInterface::class);
        $user = new class implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'anonymous';
            }
        };

        $event = new AuthorizationRequestResolveEvent($authRequest, [], $client, $user);
        $subscriber->onAuthorizationRequestResolve($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        $location = $response->getTargetUrl();
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        self::assertSame('/login', $parts['path'] ?? null);
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        self::assertSame($request->getUri(), $query['returnUrl'] ?? null);
    }

    public function testInvalidClientIdReturnsError(): void
    {
        $email = 'invalid-client-'.bin2hex(random_bytes(4)).'@example.test';
        $this->loginAsActiveMember($email);

        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => 'nonexistent_client',
            'redirect_uri' => 'https://wiki.example.test/callback',
            'response_type' => 'code',
        ]);

        $this->assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $body = $this->client()->getResponse()->getContent();
        self::assertNotFalse($body);
        $data = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('invalid_client', $data['error'] ?? null);
    }

    public function testMismatchedRedirectUriReturnsError(): void
    {
        $email = 'mismatch-redirect-'.bin2hex(random_bytes(4)).'@example.test';
        $this->loginAsActiveMember($email);

        $this->client()->request('GET', '/oauth/authorize', [
            'client_id' => self::WIKI_CLIENT_ID,
            'redirect_uri' => 'https://evil.example.com/steal-code',
            'response_type' => 'code',
            'scope' => 'wiki',
        ]);

        $this->assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $body = $this->client()->getResponse()->getContent();
        self::assertNotFalse($body);
        $data = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('invalid_client', $data['error'] ?? null);
    }
}
