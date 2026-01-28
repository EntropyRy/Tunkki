<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Member;
use App\Enum\EmailPurpose;
use App\Factory\EmailFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Functional tests for ProfileController.
 *
 * Covers:
 * - newMember: Member registration with email template (line 95)
 * - password: Password change flow
 * - apply: Active member application
 */
final class ProfileControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    /**
     * Test new member registration with Email template having subject.
     * Covers line 95 (email_content->getSubject()).
     */
    #[DataProvider('localeProvider')]
    public function testNewMemberRegistrationWithEmailSubject(string $locale): void
    {
        $this->seedClientHome($locale);

        // Create an Email template with purpose='member' that has a subject
        EmailFactory::new()->create([
            'purpose' => EmailPurpose::MEMBER_WELCOME,
            'subject' => 'Welcome to Entropy!',
            'body' => '<p>Welcome new member!</p>',
        ]);

        $path = 'en' === $locale ? '/en/profile/new' : '/profiili/uusi';
        $crawler = $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        // Fill out the registration form
        $uniqueEmail = 'test_'.bin2hex(random_bytes(4)).'@example.com';
        $form = $crawler->filter('form[name="member"]')->form([
            'member[username]' => 'testuser_'.bin2hex(random_bytes(4)),
            'member[firstname]' => 'Test',
            'member[lastname]' => 'User',
            'member[email]' => $uniqueEmail,
            'member[phone]' => '+358401234567',
            'member[locale]' => $locale,
            'member[CityOfResidence]' => 'Helsinki',
            'member[theme]' => 'dark',
            'member[user][plainPassword][first]' => 'SecurePassword123!',
            'member[user][plainPassword][second]' => 'SecurePassword123!',
        ]);

        $this->client->submit($form);

        // Should redirect to login after successful registration
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertMatchesRegularExpression('#/login(/|$)#', $location);

        // Verify member was created
        $member = $this->em()->getRepository(Member::class)->findOneBy(['email' => $uniqueEmail]);
        $this->assertNotNull($member, 'Member should be created');
        $this->assertSame($locale, $member->getLocale());
    }

    /**
     * Test password change page loads.
     */
    #[DataProvider('localeProvider')]
    public function testPasswordPageLoads(string $locale): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->create([
            'username' => 'pwduser_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/password' : '/profiili/salasana';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    /**
     * Test successful password change.
     */
    #[DataProvider('localeProvider')]
    public function testPasswordChangeSuccess(string $locale): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->create([
            'username' => 'pwdchange_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/password' : '/profiili/salasana';
        $crawler = $this->client->request('GET', $path);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            'user_password[plainPassword][first]' => 'NewSecurePassword123!',
            'user_password[plainPassword][second]' => 'NewSecurePassword123!',
        ]);

        $this->client->submit($form);

        // Should redirect to profile
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);

        // Follow redirect and check for success flash
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    /**
     * Test active member application page loads for non-active member.
     */
    #[DataProvider('localeProvider')]
    public function testApplyPageLoadsForNonActiveMember(string $locale): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->inactive()->create([
            'username' => 'applyuser_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/apply' : '/profiili/aktiiviksi';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    /**
     * Test active member is redirected when accessing apply page.
     */
    #[DataProvider('localeProvider')]
    public function testApplyPageRedirectsForActiveMember(string $locale): void
    {
        // Use loginAsActiveMember to properly set up an active member
        $email = 'activeuser_'.bin2hex(random_bytes(4)).'@example.test';
        $this->loginAsActiveMember($email);
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile/apply' : '/profiili/aktiiviksi';
        $this->client->request('GET', $path);

        // Should redirect since user is already active
        $this->assertResponseRedirects();

        // Follow redirect and check for success flash
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert-success');
    }

    /**
     * Test dashboard page loads.
     */
    #[DataProvider('localeProvider')]
    public function testDashboardLoads(string $locale): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->create([
            'username' => 'dashuser_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/dashboard' : '/yleiskatsaus';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    /**
     * Test profile index page loads.
     */
    #[DataProvider('localeProvider')]
    public function testProfileIndexLoads(string $locale): void
    {
        $memberFactory = 'en' === $locale
            ? MemberFactory::new()->english()
            : MemberFactory::new()->finnish();

        $member = $memberFactory->create([
            'username' => 'profileuser_'.bin2hex(random_bytes(4)),
            'emailVerified' => true,
        ]);

        $this->loginAsMember($member->getEmail());
        $this->seedClientHome($locale);

        $path = 'en' === $locale ? '/en/profile' : '/profiili';
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array<array{0: string}>
     */
    public static function localeProvider(): array
    {
        return [
            ['fi'],
            ['en'],
        ];
    }
}
