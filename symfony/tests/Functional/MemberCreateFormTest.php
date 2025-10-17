<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\User;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\FormErrorAssertionTrait;

/**
 * Functional tests focused solely on the member creation (registration) form.
 *
 * Scenarios covered:
 *  - Form renders with expected fields.
 *  - Successful submission creates Member + User, hashes password, and redirects to login.
 *  - Validation failures (mismatched passwords, duplicate email, invalid email, short password) re-display form with errors.
 *
 * These tests intentionally do not cover edit functionality (see MemberFormTypeTest).
 */
final class MemberCreateFormTest extends FixturesWebTestCase
{
    use FormErrorAssertionTrait;

    public function testNewMemberFormDisplaysExpectedFields(): void
    {
        $this->ensureClientReady();

        // First real request for the test
        $crawler = $this->client->request('GET', '/en/profile/new');
        for ($i = 0; $i < 3; ++$i) {
            $status = $this->client->getResponse()->getStatusCode();
            if (!\in_array($status, [301, 302, 303], true)) {
                break;
            }
            $location = $this->client->getResponse()->headers->get('Location');
            if (!$location) {
                break;
            }
            $crawler = $this->client->request('GET', $location);
        }
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Registration form should load.',
        );

        $html = $this->client->getResponse()->getContent() ?? '';

        // Core text inputs
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[username]"]')->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[firstname]"]')->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[lastname]"]')->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[email]"]')->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[phone]"]')->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[CityOfResidence]"]')->count(),
        );

        // Hidden/choice & thematic fields
        $this->assertGreaterThan(
            0,
            $crawler->filter('[name="member[locale]"]')->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('[name="member[theme]"]')->count(),
        );

        // Checkbox
        $this->assertGreaterThan(
            0,
            $crawler
                ->filter(
                    'input[type="checkbox"][name="member[StudentUnionMember]"]',
                )
                ->count(),
        );

        // Repeated password fields
        $this->assertGreaterThan(
            0,
            $crawler
                ->filter('input[name="member[user][plainPassword][first]"]')
                ->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler
                ->filter('input[name="member[user][plainPassword][second]"]')
                ->count(),
        );

        // Ensure no edit-only fields sneak in
        $this->assertStringNotContainsString(
            'name="member[allowInfoMails]"',
            $html,
        );
        $this->assertStringNotContainsString(
            'name="member[allowActiveMemberMails]"',
            $html,
        );
    }

    public function testNewMemberFormSubmissionCreatesUserAndRedirectsToLogin(): void
    {
        $this->ensureClientReady();
        $email = $this->uniqueEmail();

        // Enforce clean unauthenticated session (perform logout request first)
        $this->client->request('GET', '/en/logout');
        if (
            \in_array(
                $this->client->getResponse()->getStatusCode(),
                [301, 302, 303],
                true,
            )
        ) {
            $loc = $this->client->getResponse()->headers->get('Location');
            if ($loc) {
                $this->client->request('GET', $loc);
            }
        }

        $crawler = $this->client->request('GET', '/en/profile/new');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();

        $username = 'user_'.substr(md5($email), 0, 6);

        $form['member[username]'] = $username;
        $form['member[firstname]'] = 'Reg_'.substr(md5($email), 0, 6);
        $form['member[lastname]'] = 'User_'.substr(md5($email), 0, 6);
        $form['member[email]'] = $email;
        $form['member[phone]'] = '1234567';
        $form['member[user][plainPassword][first]'] = 'VeryStrongPass123';
        $form['member[user][plainPassword][second]'] = 'VeryStrongPass123';
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Helsinki';
        $form['member[theme]'] = 'light';
        if ($form->has('member[StudentUnionMember]')) {
            $this->setCheckbox($form, 'member[StudentUnionMember]', false);
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $redirectUrl = $this->client->getResponse()->headers->get('Location') ?? '';
            $this->assertNotEmpty(
                $redirectUrl,
                'Redirect location header missing after registration.',
            );
            // Follow redirect(s)
            $next = $redirectUrl;
            for ($i = 0; $i < 3 && $next; ++$i) {
                $this->client->request('GET', $next);
                if (!$this->client->getResponse()->isRedirect()) {
                    break;
                }
                $next = $this->client->getResponse()->headers->get('Location') ?? '';
            }
        } else {
            // Accept direct render (no redirect) as well
            $this->assertContains(
                $status,
                [200],
                "Expected redirect (3xx) or direct 200 after registration, got {$status}."
            );
        }

        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Final landing after registration should be 200.',
        );

        // Accept either login page (form present) or a check-email/verify page
        $content = $this->client->getResponse()->getContent() ?? '';
        $crawlerAfter = new \Symfony\Component\DomCrawler\Crawler($content);
        $loginFormPresent = $crawlerAfter->filter('form input[name="_username"]')->count() > 0;
        $checkEmailWording = false !== stripos($content, 'check') && false !== stripos($content, 'email');
        $verifyWording = false !== stripos($content, 'verify');
        $this->assertTrue(
            $loginFormPresent || $checkEmailWording || $verifyWording,
            'Expected to land on login page or a check-email/verify page after registration.'
        );

        // Verify persistence
        /** @var Member|null $member */
        $member = $this->em()
            ->getRepository(Member::class)
            ->findOneBy(['email' => $email]);
        $this->assertNotNull(
            $member,
            'Newly registered member should be persisted.',
        );
        $this->assertSame('Reg_'.substr(md5($email), 0, 6), $member->getFirstname());
        $this->assertSame('User_'.substr(md5($email), 0, 6), $member->getLastname());
        $this->assertSame('Helsinki', $member->getCityOfResidence());
        $this->assertSame('light', $member->getTheme());
        $this->assertNotEmpty($member->getCode(), 'Member code should be set at registration.');

        $user = $member->getUser();
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotSame(
            'VeryStrongPass123',
            $user->getPassword(),
            'Password must be hashed (should not equal plain).',
        );
    }

    public function testNewMemberFormRejectsMismatchedPasswords(): void
    {
        $this->ensureClientReady();
        $email = $this->uniqueEmail();
        $crawler = $this->client->request('GET', '/en/profile/new');
        for ($i = 0; $i < 3; ++$i) {
            $st = $this->client->getResponse()->getStatusCode();
            if (!\in_array($st, [301, 302, 303], true)) {
                break;
            }
            $loc = $this->client->getResponse()->headers->get('Location');
            if (!$loc) {
                break;
            }
            $crawler = $this->client->request('GET', $loc);
        }
        $form = $crawler->filter('form')->first()->form();

        $form['member[username]'] = 'mm_'.substr(md5($email), 0, 5);
        $form['member[firstname]'] = 'Mismatch';
        $form['member[lastname]'] = 'Case';
        $form['member[email]'] = $email;
        $form['member[phone]'] = '111222';
        $form['member[user][plainPassword][first]'] = 'PasswordOne123';
        $form['member[user][plainPassword][second]'] = 'PasswordTwo123';
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Espoo';
        $form['member[theme]'] = 'dark';
        if ($form->has('member[StudentUnionMember]')) {
            $this->setCheckbox($form, 'member[StudentUnionMember]', false);
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            $this->client->request('GET', $loc ?: '/en/profile/new');
            $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Followed redirect should load registration form.');
        } else {
            $this->assertContains(
                $status,
                [200, 422],
                'Expected to remain on form (200) or receive 422 for validation failure.',
            );
        }

        // Structural assertion (password mismatch error expected)
        $crawler = new \Symfony\Component\DomCrawler\Crawler(
            $this->client->getResponse()->getContent() ?? '',
        );
        // Use generic form error extraction to ensure at least one error reported
        if ($this->client->getResponse()->getStatusCode() >= 500) {
            $status = $this->client->getResponse()->getStatusCode();
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            $len = \strlen((string) ($this->client->getResponse()->getContent() ?? ''));
            @fwrite(\STDERR, "[MemberCreateFormTest] 5xx on mismatched passwords submission: status={$status} location={$loc} body_len={$len}\n");
            $this->fail("Registration controller returned 5xx for mismatched passwords (status {$status}).");
        }
        $errors = $this->extractAllFormErrors($crawler);
        $this->assertTrue(
            !empty($errors) || $crawler->filter('form')->count() > 0,
            'Expected errors or form re-render for mismatched passwords.'
        );

        // Ensure member not created
        $this->assertNull(
            $this->em()
                ->getRepository(Member::class)
                ->findOneBy(['email' => $email]),
            'Member should not be persisted when passwords mismatch.',
        );
    }

    public function testNewMemberFormRejectsDuplicateEmail(): void
    {
        $this->ensureClientReady();
        $existingMember = MemberFactory::new()->english()->create();
        $existingEmail = $existingMember->getEmail();

        $crawler = $this->client->request('GET', '/en/profile/new');
        for ($i = 0; $i < 3; ++$i) {
            $st = $this->client->getResponse()->getStatusCode();
            if (!\in_array($st, [301, 302, 303], true)) {
                break;
            }
            $loc = $this->client->getResponse()->headers->get('Location');
            if (!$loc) {
                break;
            }
            $crawler = $this->client->request('GET', $loc);
        }
        $form = $crawler->filter('form')->first()->form();

        $form['member[username]'] = 'dup_'.substr(md5($existingEmail), 0, 4);
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
            $this->setCheckbox($form, 'member[StudentUnionMember]', false);
        }

        $preCount = $this->countMembersByEmail($existingEmail);

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            $this->client->request('GET', $loc ?: '/en/profile/new');
            $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Followed redirect should load registration form.');
        } else {
            $this->assertContains(
                $status,
                [200, 422],
                'Expected to remain on form (200) or 422 after duplicate submission.',
            );
        }

        $crawler = new \Symfony\Component\DomCrawler\Crawler(
            $this->client->getResponse()->getContent() ?? '',
        );
        if ($this->client->getResponse()->getStatusCode() >= 500) {
            $status = $this->client->getResponse()->getStatusCode();
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            @fwrite(\STDERR, "[MemberCreateFormTest] 5xx on duplicate email submission: status={$status} location={$loc}\n");
            $this->fail("Registration controller returned 5xx for duplicate email (status {$status}).");
        }
        $errors = $this->extractAllFormErrors($crawler);
        $this->assertTrue(
            !empty($errors) || $crawler->filter('form')->count() > 0,
            'Expected errors or form re-render for duplicate email.'
        );

        $postCount = $this->countMembersByEmail($existingEmail);
        $this->assertSame(
            $preCount,
            $postCount,
            'Duplicate email must not create a new Member.',
        );
    }

    public function testNewMemberFormRejectsInvalidEmail(): void
    {
        $this->ensureClientReady();
        $crawler = $this->client->request('GET', '/en/profile/new');
        for ($i = 0; $i < 3; ++$i) {
            $st = $this->client->getResponse()->getStatusCode();
            if (!\in_array($st, [301, 302, 303], true)) {
                break;
            }
            $loc = $this->client->getResponse()->headers->get('Location');
            if (!$loc) {
                break;
            }
            $crawler = $this->client->request('GET', $loc);
        }
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();
        $email = 'invalid-'.uniqid('', true);

        $form['member[username]'] = 'invalid_'.substr(md5($email), 0, 5);
        $form['member[firstname]'] = 'Invalid';
        $form['member[lastname]'] = 'Email';
        $form['member[email]'] = $email;
        $form['member[phone]'] = '5550001';
        $form['member[user][plainPassword][first]'] = 'ValidPassword123';
        $form['member[user][plainPassword][second]'] = 'ValidPassword123';
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Helsinki';
        $form['member[theme]'] = 'light';

        $this->client->submit($form);

        // Follow up to 3 redirects if present and then assert final 200/422
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            for ($i = 0; $i < 3 && $loc; ++$i) {
                $this->client->request('GET', $loc);
                $status = $this->client->getResponse()->getStatusCode();
                if (!$this->client->getResponse()->isRedirection()) {
                    break;
                }
                $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            }
        }
        if ($status >= 500) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            @fwrite(\STDERR, "[MemberCreateFormTest] 5xx on invalid email submission: status={$status} location={$loc}\n");
            $this->fail("Registration controller returned 5xx for invalid email (status {$status}).");
        }
        $this->assertContains(
            $status,
            [200, 422],
            'Invalid email should keep user on the form (200) or yield 422.',
        );

        // Intentionally not asserting presence of specific error text; structural checks (status 200/422 and no persistence) are sufficient.

        $repo = $this->em()->getRepository(Member::class);
        $count = method_exists($repo, 'count')
            ? $repo->count(['email' => $email])
            : \count($repo->findBy(['email' => $email]));
        $this->assertSame(
            0,
            $count,
            'Invalid email submission must not create a Member.'
        );
    }

    public function testNewMemberFormRejectsTooShortPassword(): void
    {
        $this->ensureClientReady();
        $crawler = $this->client->request('GET', '/en/profile/new');
        for ($i = 0; $i < 3; ++$i) {
            $st = $this->client->getResponse()->getStatusCode();
            if (!\in_array($st, [301, 302, 303], true)) {
                break;
            }
            $loc = $this->client->getResponse()->headers->get('Location');
            if (!$loc) {
                break;
            }
            $crawler = $this->client->request('GET', $loc);
        }
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();
        $email = 'shortpass+'.uniqid().'@example.com';

        $form['member[username]'] = 'shortpw_'.substr(md5($email), 0, 5);
        $form['member[firstname]'] = 'Short';
        $form['member[lastname]'] = 'Password';
        $form['member[email]'] = $email;
        $form['member[phone]'] = '5550002';
        $form['member[user][plainPassword][first]'] = 'abc';
        $form['member[user][plainPassword][second]'] = 'abc';
        $form['member[locale]'] = 'en';
        $form['member[CityOfResidence]'] = 'Espoo';
        $form['member[theme]'] = 'dark';

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $this->client->getResponse()->headers->get('Location') ?? '';
            $this->client->request('GET', $loc ?: '/en/profile/new');
            $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Followed redirect should load registration form.');
        } else {
            if ($status >= 500) {
                $loc = $this->client->getResponse()->headers->get('Location') ?? '';
                @fwrite(\STDERR, "[MemberCreateFormTest] 5xx on too short password submission: status={$status} location={$loc}\n");
                $this->fail("Registration controller returned 5xx for too short password (status {$status}).");
            }
            $this->assertContains(
                $status,
                [200, 422],
                'Too short password should re-display the form (200) or yield 422.',
            );
        }

        $crawler = new \Symfony\Component\DomCrawler\Crawler(
            $this->client->getResponse()->getContent() ?? '',
        );
        $errors = $this->extractAllFormErrors($crawler);
        $this->assertNotEmpty(
            $errors,
            'Expected validation errors for short password.',
        );
        $this->assertTrue(
            $this->arrayContainsSubstringCI($errors, 'password'),
            'Expected a password-related validation error.',
        );

        $repo = $this->em()->getRepository(Member::class);
        $this->assertNull(
            $repo->findOneBy(['email' => $email]),
            'Too short password submission must not create a Member.',
        );
    }

    private function uniqueEmail(): string
    {
        return 'membertest+'.uniqid('', true).'@example.com';
    }

    private function countMembersByEmail(string $email): int
    {
        $repo = $this->em()->getRepository(Member::class);
        $results = method_exists($repo, 'findBy')
            ? $repo->findBy(['email' => $email])
            : [];

        return \count($results);
    }

    /**
     * Helper to (un)check a checkbox field in a DomCrawler form (order-independent safety).
     */
    private function setCheckbox(
        \Symfony\Component\DomCrawler\Form $form,
        string $name,
        bool $checked,
    ): void {
        if (!$form->has($name)) {
            return;
        }
        $field = $form[$name];
        if (
            $field instanceof \Symfony\Component\DomCrawler\Field\CheckboxFormField
        ) {
            $checked ? $field->tick() : $field->untick();
        }
    }

    /**
     * Case-insensitive array substring helper for quick validation checks.
     *
     * @param string[] $haystack
     */
    private function arrayContainsSubstringCI(
        array $haystack,
        string $needle,
    ): bool {
        $n = mb_strtolower($needle);
        foreach ($haystack as $h) {
            if (str_contains(mb_strtolower($h), $n)) {
                return true;
            }
        }

        return false;
    }
}
