<?php

declare(strict_types=1);

namespace App\Tests\Functional\OAuth;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\OAuthTestHelper;

final class UserInfoEndpointTest extends FixturesWebTestCase
{
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

    public function testMeEndpointReturnsUserInfoWithValidToken(): void
    {
        $email = \sprintf(
            'oauth-test-%s@example.test',
            bin2hex(random_bytes(4)),
        );
        $member = MemberFactory::new()
            ->active()
            ->withOAuthWikiAccess()
            ->create(['email' => $email]);
        $user = $member->getUser();

        $token = $this->createAccessToken($user, ['wiki'], self::WIKI_CLIENT_ID);

        $this->client()->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $response = $this->client()->getResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame($user->getAuthId(), $data['id']);
        $this->assertSame($user->getUsername(), $data['username']);
        $this->assertSame($email, $data['email']);
        $this->assertTrue($data['active_member']);
    }

    public function testMeEndpointReturnsNonActiveMemberStatus(): void
    {
        $member = MemberFactory::new()
            ->inactive()
            ->withOAuthWikiAccess()
            ->create();
        $user = $member->getUser();

        $token = $this->createAccessToken($user, ['wiki'], self::WIKI_CLIENT_ID);

        $this->client()->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $response = $this->client()->getResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertFalse($data['active_member']);
    }

    public function testMeEndpointDeniesAccessWithoutRequiredScope(): void
    {
        $member = MemberFactory::new()->active()->create();
        $user = $member->getUser();

        $token = $this->createAccessToken($user, ['profile'], self::WIKI_CLIENT_ID);

        $this->client()->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $response = $this->client()->getResponse();
        self::assertNotNull($response);
        self::assertSame(403, $response->getStatusCode());
    }

    public function testMeEndpointDeniesAccessWithoutToken(): void
    {
        $this->client()->request('GET', '/api/me');

        $response = $this->client()->getResponse();
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testMeEndpointDeniesAccessWithInvalidToken(): void
    {
        $this->client()->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer invalid_token_12345',
        ]);

        $response = $this->client()->getResponse();
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testMeEndpointAllowsForumScopeForNonActiveMember(): void
    {
        $member = MemberFactory::new()
            ->inactive()
            ->withOAuthForumAccess()
            ->create();
        $user = $member->getUser();

        $token = $this->createAccessToken($user, ['forum'], self::FORUM_CLIENT_ID);

        $this->client()->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        $response = $this->client()->getResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertFalse($data['active_member']);
    }
}
