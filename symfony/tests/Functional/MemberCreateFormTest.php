<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

/**
 * Functional tests focused solely on the member creation (registration) form.
 *
 * Scenarios covered:
 *  - Form renders with expected fields.
 *  - Successful submission creates Member + User, hashes password, and redirects to login.
 *  - Mismatched passwords cause validation failure (no redirect).
 *  - Duplicate email submission (existing fixture email) does not create a new member and does not redirect.
 *
 * These tests intentionally do not cover edit functionality (see MemberFormTypeTest).
 */
final class MemberCreateFormTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');
    }

    public function testNewMemberFormDisplaysExpectedFields(): void
    {
        $crawler = $this->client->request('GET', '/en/profile/new');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Expected 200 loading registration form.');

        $html = $this->client->getResponse()->getContent() ?? '';

        // Core text fields
        $this->assertStringContainsString('name="member[username]"', $html);
        $this->assertStringContainsString('name="member[firstname]"', $html);
        $this->assertStringContainsString('name="member[lastname]"', $html);
        $this->assertStringContainsString('name="member[email]"', $html);
        $this->assertStringContainsString('name="member[phone]"', $html);
        $this->assertStringContainsString('name="member[CityOfResidence]"', $html);

        // Hidden/choice + thematic fields
        $this->assertStringContainsString('name="member[locale]"', $html);
        $this->assertStringContainsString('name="member[theme]"', $html);

        // Checkbox
        $this->assertStringContainsString('name="member[StudentUnionMember]"', $html);

        // Repeated password fields
        $this->assertStringContainsString('name="member[user][plainPassword][first]"', $html);
        $this->assertStringContainsString('name="member[user][plainPassword][second]"', $html);

        // Ensure no edit-only fields sneak in
        $this->assertStringNotContainsString('name="member[allowInfoMails]"', $html);
        $this->assertStringNotContainsString('name="member[allowActiveMemberMails]"', $html);
    }

    public function testNewMemberFormSubmissionCreatesUserAndRedirectsToLogin(): void
    {
        $email = $this->uniqueEmail();
        $crawler = $this->client->request('GET', '/en/profile/new');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();

        $username = 'user_' . substr(md5($email), 0, 6);

        $form['member[username]'] = $username;
        $form['member[firstname]'] = 'Reg';
        $form['member[lastname]'] = 'User';
        $form['member[email]'] = $email;
        $form['member[phone]'] = '1234567';
        $form['member[user][plainPassword][first]'] = 'VeryStrongPass123';
        $form['member[user][plainPassword][second]'] = 'VeryStrongPass123';
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Helsinki';
        $form['member[theme]'] = 'light';
        if ($form->has('member[StudentUnionMember]')) {
            // leave default (likely unchecked)
            $form['member[StudentUnionMember]']->untick();
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303], true), 'Expected redirect after successful registration (got ' . $status . ').');

        $redirectUrl = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty($redirectUrl, 'Redirect location header missing after registration.');

        // Follow redirect (login page)
        $this->client->request('GET', $redirectUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Expected login page to load after redirect.');

        // Verify persistence
        $memberRepo = $this->em()->getRepository(Member::class);
        /** @var Member|null $member */
        $member = $memberRepo->findOneBy(['email' => $email]);
        $this->assertNotNull($member, 'Newly registered member should be persisted.');
        $this->assertSame('Reg', $member->getFirstname());
        $this->assertSame('User', $member->getLastname());
        $this->assertSame('Helsinki', $member->getCityOfResidence());
        $this->assertSame('light', $member->getTheme());

        $user = $member->getUser();
        $this->assertInstanceOf(User::class, $user, 'Member should have a linked User.');
        $this->assertNotSame(
            'VeryStrongPass123',
            $user->getPassword(),
            'Stored password must be hashed (should not equal plain password).'
        );
    }

    public function testNewMemberFormRejectsMismatchedPasswords(): void
    {
        $email = $this->uniqueEmail();
        $crawler = $this->client->request('GET', '/en/profile/new');
        $form = $crawler->filter('form')->first()->form();

        $form['member[username]'] = 'mm_' . substr(md5($email), 0, 5);
        $form['member[firstname]'] = 'Mismatch';
        $form['member[lastname]'] = 'Case';
        $form['member[email]'] = $email;
        $form['member[phone]'] = '111222';
        $form['member[user][plainPassword][first]'] = 'PasswordOne123';
        $form['member[user][plainPassword][second]'] = 'PasswordTwo123'; // mismatch
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Espoo';
        $form['member[theme]'] = 'dark';
        if ($form->has('member[StudentUnionMember]')) {
            $form['member[StudentUnionMember]']->untick();
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        // Expect non-redirect (likely 200 with form + errors)
        $this->assertTrue(in_array($status, [200, 422], true), 'Expected 200 or 422 (validation error) for mismatched passwords, got ' . $status . '.');

        $content = $this->client->getResponse()->getContent() ?? '';
        $this->assertTrue(
            str_contains($content, 'passwords_need_to_match') ||
            str_contains($content, 'The password fields must match'),
            'Expected mismatch validation message (translation key or rendered text) in response content.'
        );

        // Ensure member not created
        $memberRepo = $this->em()->getRepository(Member::class);
        $this->assertNull(
            $memberRepo->findOneBy(['email' => $email]),
            'Member should not be persisted when passwords mismatch.'
        );
    }

    public function testNewMemberFormRejectsDuplicateEmail(): void
    {
        // Known fixture email from UserFixtures
        $existingEmail = 'testuser@example.com';

        $crawler = $this->client->request('GET', '/en/profile/new');
        $form = $crawler->filter('form')->first()->form();

        $form['member[username]'] = 'dup_' . substr(md5($existingEmail), 0, 4);
        $form['member[firstname]'] = 'Dupe';
        $form['member[lastname]'] = 'Check';
        $form['member[email]'] = $existingEmail;
        $form['member[phone]'] = '999000';
        $form['member[user][plainPassword][first]'] = 'DuplicatePass123';
        $form['member[user][plainPassword][second]'] = 'DuplicatePass123';
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Vantaa';
        $form['member[theme]'] = 'light';
        if ($form->has('member[StudentUnionMember]')) {
            $form['member[StudentUnionMember]']->untick();
        }

        $preCount = $this->countMembersByEmail($existingEmail);

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        // Duplicate should not redirect; expect to stay on form
        $this->assertTrue(in_array($status, [200, 422], true), 'Expected 200 or 422 after duplicate email attempt, got ' . $status . '.');

        $postCount = $this->countMembersByEmail($existingEmail);
        $this->assertSame(
            $preCount,
            $postCount,
            'Duplicate email submission must not create an additional Member.'
        );
    }

    private function uniqueEmail(): string
    {
        return 'membertest+' . uniqid('', true) . '@example.com';
    }

    private function countMembersByEmail(string $email): int
    {
        return $this->em()
            ->getRepository(Member::class)
            ->count(['email' => $email]);
    }
}
