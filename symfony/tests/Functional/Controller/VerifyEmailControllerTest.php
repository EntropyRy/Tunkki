<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Member;
use App\Entity\User;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Zenstruck\Foundry\Persistence\Proxy;
use App\Controller\VerifyEmailController;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('verify-email')]
final class VerifyEmailControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testVerifyRedirectsWhenIdMissing(): void
    {
        $this->client->request('GET', '/vahvista/sahkoposti');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/login', parse_url($location, \PHP_URL_PATH));
    }

    public function testVerifyRedirectsWhenUserNotFound(): void
    {
        $this->client->request('GET', '/vahvista/sahkoposti?id=999999');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/login', parse_url($location, \PHP_URL_PATH));
    }

    public function testVerifyMarksMemberVerifiedWithValidSignature(): void
    {
        $memberProxy = MemberFactory::new()->create([
            'emailVerified' => false,
        ]);
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $member->getUser();

        $path = $this->buildSignedPath($user, ['id' => $user->getId()]);

        $this->client->request('GET', $path);

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/login', parse_url($location, \PHP_URL_PATH));

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Member::class)->find($member->getId());
        self::assertInstanceOf(Member::class, $reloaded);
        self::assertTrue($reloaded->isEmailVerified());
    }

    public function testVerifyUsesMemberIdFallback(): void
    {
        $this->createMemberWithoutUser();

        $memberProxy = MemberFactory::new()->create([
            'emailVerified' => false,
        ]);
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $member->getUser();

        $path = $this->buildSignedPath($user, ['id' => $member->getId()]);

        $this->client->request('GET', $path);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Member::class)->find($member->getId());
        self::assertInstanceOf(Member::class, $reloaded);
        self::assertTrue($reloaded->isEmailVerified());
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $memberProxy = MemberFactory::new()->create([
            'emailVerified' => false,
        ]);
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;
        $user = $member->getUser();

        $path = $this->buildSignedPath($user, ['id' => $user->getId()]);
        $tampered = $this->tamperSignature($path);

        $this->client->request('GET', $tampered);

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/login', parse_url($location, \PHP_URL_PATH));

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(Member::class)->find($member->getId());
        self::assertInstanceOf(Member::class, $reloaded);
        self::assertFalse($reloaded->isEmailVerified());
    }

    public function testResendRedirectsWhenUnauthenticated(): void
    {
        $this->client->request('GET', '/profiili/laheta-vahvistus');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/login', parse_url($location, \PHP_URL_PATH));
    }

    public function testResendRedirectsWhenNoUserInContext(): void
    {
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(null);

        $controller = new VerifyEmailController(
            static::getContainer()->get(EmailVerifier::class),
            static::getContainer()->get(EntityManagerInterface::class),
        );
        $controller->setContainer(static::getContainer());

        $response = $controller->resend(
            static::getContainer()->get(TranslatorInterface::class),
        );

        self::assertSame('/login', $response->getTargetUrl());
    }

    public function testResendRedirectsWhenAlreadyVerified(): void
    {
        $memberProxy = MemberFactory::new()->create([
            'emailVerified' => true,
            'locale' => 'fi',
        ]);
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;

        $this->loginAsEmail($member->getEmail());

        $this->client->request('GET', '/profiili/laheta-vahvistus');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/profiili', parse_url($location, \PHP_URL_PATH));
    }

    public function testResendRedirectsForUnverifiedMember(): void
    {
        $memberProxy = MemberFactory::new()->create([
            'emailVerified' => false,
            'locale' => 'fi',
        ]);
        $member = $memberProxy instanceof Proxy ? $memberProxy->_real() : $memberProxy;

        $this->loginAsEmail($member->getEmail());

        $this->client->request('GET', '/profiili/laheta-vahvistus');

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        self::assertNotNull($location);
        self::assertSame('/profiili', parse_url($location, \PHP_URL_PATH));
    }

    private function buildSignedPath(User $user, array $extraParams): string
    {
        $helper = static::getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $helper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            $user->getEmail() ?? '',
            $extraParams,
        );

        $signedUrl = $signature->getSignedUrl();
        $path = (string) parse_url($signedUrl, \PHP_URL_PATH);
        $query = parse_url($signedUrl, \PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }

    private function tamperSignature(string $url): string
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);
        $query = (string) parse_url($url, \PHP_URL_QUERY);
        parse_str($query, $params);
        $params['signature'] = 'bad'.$params['signature'];

        return $path.'?'.http_build_query($params);
    }

    private function createMemberWithoutUser(): void
    {
        $member = new Member();
        $member
            ->setFirstname('Legacy')
            ->setLastname('Member')
            ->setEmail('legacy_'.bin2hex(random_bytes(4)).'@example.test')
            ->setLocale('fi')
            ->setCode('LEGACY'.bin2hex(random_bytes(2)));

        $this->em()->persist($member);
        $this->em()->flush();
    }
}
