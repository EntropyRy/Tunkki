<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Factory\MemberFactory;
use App\Service\EPicsService;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProfileControllerEpicsPasswordTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/en/profile/epics/password');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $path = parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH);
        self::assertContains($path, ['/login', '/en/login']);
    }

    #[DataProvider('localeAndPaths')]
    public function testUnverifiedEmailUserRedirectedToVerification(string $locale, string $path): void
    {
        $this->loginAsMemberWithUnverifiedEmail();
        $this->seedClientHome($locale);

        $this->client->request('GET', $path);

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);

        $expectedPath = 'en' === $locale ? '/en/profile/resend-verification' : '/profiili/laheta-vahvistus';
        $this->assertStringContainsString($expectedPath, $location);
    }

    #[DataProvider('localeAndPaths')]
    public function testGetRendersEpicsUsernameResolvedFromMemberUsername(string $locale, string $path): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->inactive()->create([
            'username' => 'my-user',
            'epicsUsername' => null,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $this->client->assertSelectorExists('form');
        $this->client->assertSelectorExists('p strong');
        $this->client->assertSelectorTextContains('code', 'my-user');
    }

    #[DataProvider('localeAndPaths')]
    public function testSuccessfulSubmitSetsEpicsUsernameAndAddsSuccessFlash(string $locale, string $path): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->inactive()->create([
            'username' => 'my-user',
            'epicsUsername' => null,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $calls = [];
        static::getContainer()->set(EPicsService::class, $this->createEpicsServiceStub(
            success: true,
            calls: $calls,
        ));

        $crawler = $this->client->request('GET', $path);
        $form = $crawler->filter('form')->form([
            'e_pics_password[plainPassword][first]' => 'password123',
            'e_pics_password[plainPassword][second]' => 'password123',
        ]);

        $this->client->submit($form);
        $response = $this->client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->getProfilePath($locale), parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH));

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-success');
        $this->client->assertSelectorTextContains('.alert.alert-success', $this->getExpectedSuccessFlashMessage($locale));

        $reloaded = $this->em()->getRepository(\App\Entity\Member::class)->find($member->getId());
        self::assertInstanceOf(\App\Entity\Member::class, $reloaded);
        self::assertSame('my-user', $reloaded->getEpicsUsername());

        self::assertSame('my-user', $calls['username'] ?? null);
        self::assertSame('password123', $calls['password'] ?? null);
    }

    #[DataProvider('localeAndPaths')]
    public function testFailedSubmitDoesNotChangeEpicsUsernameAndAddsDangerFlash(string $locale, string $path): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->inactive()->create([
            'username' => 'my-user',
            'epicsUsername' => null,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        static::getContainer()->set(EPicsService::class, $this->createEpicsServiceStub(
            success: false,
        ));

        $crawler = $this->client->request('GET', $path);
        $form = $crawler->filter('form')->form([
            'e_pics_password[plainPassword][first]' => 'password123',
            'e_pics_password[plainPassword][second]' => 'password123',
        ]);

        $this->client->submit($form);
        $response = $this->client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->getProfilePath($locale), parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH));

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-danger');
        $this->client->assertSelectorTextContains('.alert.alert-danger', $this->getExpectedDangerFlashMessage($locale));

        $reloaded = $this->em()->getRepository(\App\Entity\Member::class)->find($member->getId());
        self::assertInstanceOf(\App\Entity\Member::class, $reloaded);
        self::assertNull($reloaded->getEpicsUsername());
    }

    /**
     * @param array<string, string> $calls
     */
    private function createEpicsServiceStub(bool $success, array &$calls = []): EPicsService
    {
        $calls = [];

        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$calls, $success): MockResponse {
            if ('GET' === $method && !str_contains($url, '/api/v2/')) {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'set-cookie: lychee_session=abc; path=/; HttpOnly; samesite=lax',
                        'set-cookie: XSRF-TOKEN=xsrf; path=/; samesite=lax',
                    ],
                ]);
            }

            if ('POST' === $method && str_contains($url, '/api/v2/Auth::login')) {
                return new MockResponse('', ['http_code' => 204]);
            }

            if ('GET' === $method && str_contains($url, '/api/v2/UserManagement')) {
                return new MockResponse('[]', ['http_code' => 200]);
            }

            if (
                \in_array($method, ['POST', 'PATCH'], true)
                && str_contains($url, '/api/v2/UserManagement')
            ) {
                $payload = $options['json'] ?? null;
                if (null === $payload && isset($options['body'])) {
                    $body = $options['body'];
                    if (\is_string($body)) {
                        $decoded = json_decode($body, true);
                        $payload = \is_array($decoded) ? $decoded : null;
                    } elseif (\is_array($body)) {
                        $payload = $body;
                    }
                }
                if (!\is_array($payload)) {
                    $payload = [];
                }

                $calls['username'] = (string) ($payload['username'] ?? '');
                $calls['password'] = (string) ($payload['password'] ?? '');

                return new MockResponse('', ['http_code' => $success ? 204 : 500]);
            }

            return new MockResponse('', ['http_code' => 500]);
        });

        return new EPicsService($http);
    }

    /**
     * @return array<array{0: string, 1: string}>
     */
    public static function localeAndPaths(): array
    {
        return [
            ['en', '/en/profile/epics/password'],
            ['fi', '/profiili/epics/salasana'],
        ];
    }

    private function getProfilePath(string $locale): string
    {
        return 'en' === $locale ? '/en/profile' : '/profiili';
    }

    private function getExpectedSuccessFlashMessage(string $locale): string
    {
        return 'en' === $locale ? 'ePics password saved.' : 'ePics-salasana tallennettu.';
    }

    private function getExpectedDangerFlashMessage(string $locale): string
    {
        return 'en' === $locale
            ? 'Could not set ePics password. Please try again or contact support.'
            : 'ePics-salasanan asetus epäonnistui. Yritä uudelleen tai ota yhteyttä ylläpitoon.';
    }
}
