<?php

declare(strict_types=1);

namespace App\Tests\Functional\OAuth;

use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use App\Tests\Support\OAuthTestHelper;

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
        $this->loginAsActiveMember('wiki_active@example.test');

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
        $this->assertStringStartsWith('https://wiki.example.test/callback', $location);
        $this->assertStringContainsString('code=', $location);
        $this->assertStringContainsString('state=wiki_state', $location);
    }

    public function testNonActiveMemberCannotAuthorizeWikiClient(): void
    {
        MemberFactory::new()
            ->inactive()
            ->finnish()
            ->create(['email' => 'nonactive_wiki@example.test']);

        [$user] = $this->loginAsMember('nonactive_wiki@example.test');
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
        $this->assertStringContainsString('/profiili', $location);

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
        $this->assertStringContainsString('/en/profile', $location);
    }

    public function testNonActiveMemberCanAuthorizeForumClient(): void
    {
        $this->loginAsMember('forum_nonactive@example.test');

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
        $this->assertStringStartsWith('https://forum.example.test/callback', $location);
        $this->assertStringContainsString('state=forum_state', $location);
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
        $this->assertStringContainsString('/login', $location);
    }

    public function testInvalidClientIdReturnsError(): void
    {
        $this->loginAsActiveMember('invalid_client@example.test');

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
        $this->loginAsActiveMember('mismatch_redirect@example.test');

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
