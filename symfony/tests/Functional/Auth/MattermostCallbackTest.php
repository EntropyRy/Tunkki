<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Functional tests for Mattermost OAuth callback authentication flow.
 *
 * Uses Mockery to mock HTTP client as recommended by oauth2-client documentation:
 * https://deepwiki.com/thephpleague/oauth2-client/6-testing
 *
 * Works with ParaTest because mocking happens before requests, not after container init.
 */
final class MattermostCallbackTest extends FixturesWebTestCase
{
    private const CALLBACK_ROUTE = '/oauth/check';
    private const OAUTH_STATE = 'test_state';

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test existing user with MattermostId authenticates successfully.
     *
     * Covers: MattermostAuthenticator.php:73-84 (existing user lookup)
     */
    public function testExistingUserWithMattermostIdAuthenticates(): void
    {
        // Create member with linked Mattermost account
        $member = MemberFactory::new()->active()->create();
        $user = $member->getUser();
        $user->setMattermostId('mm_existing_123');
        $this->em()->persist($user);
        $this->em()->flush();

        // Mock Mattermost API responses
        $this->mockMattermostHttpClient([
            'id' => 'mm_existing_123',
            'email' => $user->getEmail(),
            'username' => 'existinguser',
        ]);

        // Simulate OAuth callback
        $this->seedOauthState(self::OAUTH_STATE);
        $this->client()->request('GET', self::CALLBACK_ROUTE, [
            'code' => 'test_code',
            'state' => self::OAUTH_STATE,
        ]);

        // Should authenticate and redirect to dashboard
        $response = $this->client()->getResponse();
        $this->assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location');
        $this->assertThat(
            $location,
            $this->logicalOr(
                $this->stringContains('/yleiskatsaus'),
                $this->stringContains('/dashboard'),
            ),
            'Should redirect to dashboard (locale-aware)',
        );

        $this->assertAuthenticated('User should be authenticated');
    }

    /**
     * Test account linking when user exists with matching email.
     *
     * Covers: MattermostAuthenticator.php:102-127 (email-based linking)
     */
    public function testEmailMatchLinksAccountWithMattermostId(): void
    {
        $member = MemberFactory::new()->active()->create([
            'email' => 'linker@example.com',
        ]);
        $user = $member->getUser();

        $this->assertNull($user->getMattermostId());

        $this->mockMattermostHttpClient([
            'id' => 'mm_new_456',
            'email' => 'linker@example.com',
            'username' => 'newuser',
        ]);

        $this->seedOauthState(self::OAUTH_STATE);
        $this->client()->request('GET', self::CALLBACK_ROUTE, [
            'code' => 'test_code',
            'state' => self::OAUTH_STATE,
        ]);

        $this->assertResponseStatusCodeSame(302);

        $this->em()->refresh($user);
        $this->assertSame('mm_new_456', $user->getMattermostId());
        $this->assertAuthenticated('User should be authenticated');
    }

    /**
     * Test username is updated from Mattermost.
     *
     * Covers: MattermostAuthenticator.php:107-111 (username sync)
     */
    public function testUsernameUpdateFromMattermost(): void
    {
        $member = MemberFactory::new()->active()->create(['username' => 'olduser']);
        $user = $member->getUser();
        $user->setMattermostId('mm_update_789');
        $this->em()->persist($user);
        $this->em()->flush();

        $this->mockMattermostHttpClient([
            'id' => 'mm_update_789',
            'email' => $user->getEmail(),
            'username' => 'newuser',
        ]);

        $this->seedOauthState(self::OAUTH_STATE);
        $this->client()->request('GET', self::CALLBACK_ROUTE, [
            'code' => 'test_code',
            'state' => self::OAUTH_STATE,
        ]);

        $this->assertResponseStatusCodeSame(302);

        $this->em()->refresh($member);
        $this->assertSame('newuser', $member->getUsername());
    }

    /**
     * Test no matching user redirects to login.
     *
     * Covers: MattermostAuthenticator.php:129-130
     */
    public function testNoMatchingUserRedirectsToLogin(): void
    {
        $this->mockMattermostHttpClient([
            'id' => 'mm_unknown',
            'email' => 'unknown@example.com',
            'username' => 'unknown',
        ]);

        $this->seedOauthState(self::OAUTH_STATE);
        $this->client()->request('GET', self::CALLBACK_ROUTE, [
            'code' => 'test_code',
            'state' => self::OAUTH_STATE,
        ]);

        $response = $this->client()->getResponse();
        $this->assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/login', $location);

        $this->assertNotAuthenticated('Unknown user should not authenticate');
    }

    /**
     * Test missing email field validation.
     *
     * Covers: MattermostAuthenticator.php:62-67 (safety fix)
     */
    public function testMissingEmailFieldThrowsException(): void
    {
        $this->mockMattermostHttpClient([
            'id' => 'mm_incomplete',
            'username' => 'incomplete',
            // email missing
        ]);

        $this->seedOauthState(self::OAUTH_STATE);
        $this->client()->request('GET', self::CALLBACK_ROUTE, [
            'code' => 'test_code',
            'state' => self::OAUTH_STATE,
        ]);

        $response = $this->client()->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $this->assertNotAuthenticated('Incomplete payload should not authenticate');
    }

    /**
     * Test missing id field validation.
     *
     * Covers: MattermostAuthenticator.php:62-67 (safety fix)
     */
    public function testMissingIdFieldThrowsException(): void
    {
        $this->mockMattermostHttpClient([
            'email' => 'test@example.com',
            'username' => 'incomplete',
            // id missing
        ]);

        $this->seedOauthState(self::OAUTH_STATE);
        $this->client()->request('GET', self::CALLBACK_ROUTE, [
            'code' => 'test_code',
            'state' => self::OAUTH_STATE,
        ]);

        $response = $this->client()->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
        $this->assertNotAuthenticated('Incomplete payload should not authenticate');
    }

    /**
     * Mock Mattermost HTTP responses using PHPUnit mocks.
     *
     * Following oauth2-client testing guide but using PHPUnit instead of Mockery:
     * https://deepwiki.com/thephpleague/oauth2-client/6-testing
     *
     * @param array<string, string> $userData User data from Mattermost API
     */
    private function mockMattermostHttpClient(array $userData): void
    {
        // Mock access token response body
        $tokenBody = $this->createStub(StreamInterface::class);
        $tokenBody->method('__toString')->willReturn(json_encode([
            'access_token' => 'mock_token_' . bin2hex(random_bytes(8)),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], \JSON_THROW_ON_ERROR));

        // Mock access token response
        $tokenResponse = $this->createStub(ResponseInterface::class);
        $tokenResponse->method('getBody')->willReturn($tokenBody);
        $tokenResponse->method('getHeader')->willReturn(['application/json']);
        $tokenResponse->method('getStatusCode')->willReturn(200);

        // Mock user info response body
        $userBody = $this->createStub(StreamInterface::class);
        $userBody->method('__toString')->willReturn(json_encode($userData, \JSON_THROW_ON_ERROR));

        // Mock user info response
        $userResponse = $this->createStub(ResponseInterface::class);
        $userResponse->method('getBody')->willReturn($userBody);
        $userResponse->method('getHeader')->willReturn(['application/json']);
        $userResponse->method('getStatusCode')->willReturn(200);

        // Mock HTTP client that returns token response then user response
        $mockClient = $this->createStub(ClientInterface::class);
        $mockClient->method('send')
            ->willReturnOnConsecutiveCalls($tokenResponse, $userResponse);

        // Inject mock client into OAuth provider
        $container = static::getContainer();
        $clientRegistry = $container->get('knpu.oauth2.registry');
        $oauthClient = $clientRegistry->getClient('mattermost');

        // Access provider via reflection
        $reflection = new \ReflectionClass($oauthClient);
        $providerProp = $reflection->getProperty('provider');
        $providerProp->setAccessible(true);
        $provider = $providerProp->getValue($oauthClient);

        // Inject mock HTTP client
        $provider->setHttpClient($mockClient);
    }

    private function seedOauthState(string $state): void
    {
        /** @var \Symfony\Component\HttpFoundation\Session\SessionFactoryInterface $sessionFactory */
        $sessionFactory = static::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();

        $session->set(OAuth2Client::OAUTH2_SESSION_STATE_KEY, $state);
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client()->getCookieJar()->set($cookie);
    }
}
