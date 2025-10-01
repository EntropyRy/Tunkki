<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;

/**
 * Functional tests for member profile editing (unified MemberType in edit mode).
 *
 * Ensures:
 *  - Edit form excludes embedded user password fields.
 *  - Edit form includes allowInfoMails (and conditionally allowActiveMemberMails).
 *  - Submissions persist updated profile + preference values.
 */
final class MemberFormTypeTest extends FixturesWebTestCase
{
    private ?SiteAwareKernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new SiteAwareKernelBrowser(static::bootKernel());
        $this->client->setServerParameter('HTTP_HOST', 'localhost');

        // Clear EntityManager to prevent state pollution from previous tests
        $this->em()->clear();
    }

    public function testEditMemberFormContainsAllowInfoMailsAndNotUserPassword(): void
    {
        $user = $this->loadUserByEmail('testuser@example.com');
        $this->client->loginUser($user);

        $this->client->request('GET', '/en/profile/edit');
        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $html = $response->getContent() ?? '';
        $this->assertStringNotContainsString('name="member[user][plainPassword][first]"', $html);
        $this->assertStringNotContainsString('name="member[user][plainPassword][second]"', $html);
        $this->assertStringContainsString('name="member[allowInfoMails]"', $html);
    }

    public function testEditMemberFormShowsActiveMemberMailCheckboxWhenActive(): void
    {
        // Create isolated user to avoid fixture relational side-effects
        $user = $this->createIsolatedUser('activeedit+'.uniqid().'@example.com', isActive: true);

        $this->client->loginUser($user);
        $this->client->request('GET', '/en/profile/edit');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $html = $this->client->getResponse()->getContent() ?? '';
        $this->assertStringContainsString(
            'name="member[allowActiveMemberMails]"',
            $html,
            'Active member edit form must include allowActiveMemberMails.'
        );
    }

    public function testEditMemberFormSubmissionUpdatesPreferences(): void
    {
        $user = $this->createIsolatedUser('editprefs+'.uniqid().'@example.com', isActive: true);
        $this->client->loginUser($user);

        // Load edit form
        $crawler = $this->client->request('GET', '/en/profile/edit');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $member = $user->getMember();
        $originalLocale = $member->getLocale();

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Edit form should exist.');
        $form = $formNode->form();

        // Modify fields
        $form['member[firstname]'] = 'ChangedFirst';
        $form['member[lastname]'] = 'ChangedLast';
        $form['member[email]'] = $member->getEmail(); // unchanged
        $form['member[phone]'] = '555-000';
        $form['member[locale]'] = 'en' === $originalLocale ? 'fi' : 'en';
        $form['member[CityOfResidence]'] = 'Espoo';
        if ($form->has('member[StudentUnionMember]')) {
            $form['member[StudentUnionMember]']->untick();
        }
        $form['member[theme]'] = 'dark';
        if ($form->has('member[allowInfoMails]')) {
            $form['member[allowInfoMails]']->untick();
        }
        if ($form->has('member[allowActiveMemberMails]')) {
            $form['member[allowActiveMemberMails]']->untick();
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertSame(302, $status, 'Edit submission should redirect (got '.$status.').');

        if ($loc = $this->client->getResponse()->headers->get('Location')) {
            $this->client->request('GET', $loc);
        }

        $this->em()->refresh($member);
        $this->assertSame('ChangedFirst', $member->getFirstname());
        $this->assertSame('ChangedLast', $member->getLastname());
        $this->assertSame('Espoo', $member->getCityOfResidence());
        $this->assertSame('dark', $member->getTheme());
        $this->assertFalse($member->isAllowInfoMails(), 'allowInfoMails should be false after submission.');
        if ($member->getIsActiveMember()) {
            $this->assertFalse($member->isAllowActiveMemberMails(), 'allowActiveMemberMails should be false after submission.');
        }
    }

    private function loadUserByEmail(string $email): User
    {
        $repo = $this->em()->getRepository(User::class);
        /** @var User[] $all */
        $all = $repo->findAll();
        $user = null;
        foreach ($all as $candidate) {
            $member = $candidate->getMember();
            if ($member && $member->getEmail() === $email) {
                $user = $candidate;
                break;
            }
        }
        $this->assertNotNull($user, sprintf('User with (member) email %s should exist in fixtures.', $email));

        return $user;
    }

    private function createIsolatedUser(string $email, bool $isActive = false): User
    {
        $em = $this->em();

        $user = new User();
        $user->setRoles([]);
        $user->setAuthId('test-'.uniqid());
        $user->setPassword('temp-hash');

        $member = new Member();
        $member->setEmail($email);
        $member->setFirstname('Iso');
        $member->setLastname('User');
        $member->setUsername('iso_'.substr(md5($email), 0, 5));
        $member->setLocale('en');
        $member->setCode('ISO'.substr(md5($email), 0, 6));
        $member->setEmailVerified(true);
        $member->setIsActiveMember($isActive);

        $member->setUser($user);
        $user->setMember($member);

        $em->persist($user);
        $em->persist($member);
        $em->flush();

        return $user;
    }

    public function testLocaleSwitchOnEditRedirectsToLocalizedProfile(): void
    {
        $user = $this->createIsolatedUser('localechange+'.uniqid().'@example.com', isActive: false);
        $this->client->loginUser($user);

        // Load edit form in English
        $crawler = $this->client->request('GET', '/en/profile/edit');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Edit form should load (en).');

        $member = $user->getMember();
        $this->assertSame('en', $member->getLocale(), 'Precondition: member locale should start as en.');

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Edit form element should exist.');
        $form = $formNode->form();

        // Keep existing personal data but change locale + required fields
        $form['member[firstname]'] = $member->getFirstname();
        $form['member[lastname]'] = $member->getLastname();
        $form['member[email]'] = $member->getEmail();
        if ($form->has('member[phone]')) {
            $form['member[phone]'] = $member->getPhone() ?? '';
        }
        $form['member[CityOfResidence]'] = $member->getCityOfResidence() ?? 'Espoo';
        $form['member[theme]'] = 'dark'; // required choice
        $form['member[locale]'] = 'fi';  // switch locale
        if ($form->has('member[StudentUnionMember]')) {
            // leave as is (do not change tick state)
        }
        if ($form->has('member[allowInfoMails]')) {
            // leave as is to avoid altering preference semantics
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertSame(302, $status, 'Locale change edit should redirect (got '.$status.').');

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty($location, 'Redirect location missing after locale change.');
        // Expect Finnish profile path fragment
        $this->assertStringContainsString('/profiili', $location, 'Redirect should point to Finnish profile page after locale switch.');

        $this->client->request('GET', $location);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Localized profile page should load.');

        // Refresh entity and assert locale persisted
        $this->em()->refresh($member);
        $this->assertSame('fi', $member->getLocale(), 'Member locale should be updated to fi.');
    }

    public function testLocaleRevertOnEditRedirectsToEnglishProfile(): void
    {
        $user = $this->createIsolatedUser('localerevert+'.uniqid().'@example.com', isActive: false);
        $this->client->loginUser($user);

        // Step 1: switch locale from en -> fi
        $crawler = $this->client->request('GET', '/en/profile/edit');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Edit form (en) should load.');
        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Edit form should exist (en).');
        $form = $formNode->form();

        $member = $user->getMember();
        $form['member[firstname]'] = $member->getFirstname();
        $form['member[lastname]'] = $member->getLastname();
        $form['member[email]'] = $member->getEmail();
        if ($form->has('member[phone]')) {
            $form['member[phone]'] = $member->getPhone() ?? '';
        }
        $form['member[CityOfResidence]'] = $member->getCityOfResidence() ?? 'Espoo';
        $form['member[theme]'] = 'dark';
        $form['member[locale]'] = 'fi';

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303], true), 'Locale switch to fi should redirect (got '.$status.').');
        $loc = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty($loc, 'Redirect location missing after switching to fi.');
        $this->client->request('GET', $loc);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'Finnish profile page should load.');
        $this->assertStringContainsString('/profiili', $this->client->getRequest()->getPathInfo(), 'Expected Finnish profile path after switch.');

        $this->em()->refresh($member);
        $this->assertSame('fi', $member->getLocale(), 'Member locale should now be fi.');

        // Step 2: revert fi -> en
        // Try a sequence of possible localized edit paths.
        $pathsTried = [];
        $responseOk = false;

        // Primary localized (fi) edit route
        $paths = [
            '/en/profile/edit',
        ];

        $crawler = null;
        foreach ($paths as $p) {
            $pathsTried[] = $p;
            $crawler = $this->client->request('GET', $p);
            if (200 === $this->client->getResponse()->getStatusCode() && $crawler->filter('form')->count() > 0) {
                $responseOk = true;
                break;
            }
        }

        // If none returned a usable form, fail with diagnostic.
        if (!$responseOk) {
            $status = $this->client->getResponse()->getStatusCode();
            $this->fail(
                'Could not load edit form for locale revert. Last status='.$status.
                ' Paths tried: '.implode(', ', $pathsTried)
            );
        }

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(0, $formNode->count(), 'Edit form should exist for locale revert.');
        $form = $formNode->form();

        $form['member[firstname]'] = $member->getFirstname();
        $form['member[lastname]'] = $member->getLastname();
        $form['member[email]'] = $member->getEmail();
        if ($form->has('member[phone]')) {
            $form['member[phone]'] = $member->getPhone() ?? '';
        }
        $form['member[CityOfResidence]'] = $member->getCityOfResidence() ?? 'Espoo';
        $form['member[theme]'] = 'light';
        $form['member[locale]'] = 'en';

        $this->client->submit($form);
        $status2 = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status2, [302, 303], true), 'Revert locale to en should redirect (got '.$status2.').');
        $loc2 = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty($loc2, 'Redirect location missing after reverting to en.');
        $this->assertStringContainsString('/profile', $loc2, 'Expected English profile route in redirect after revert.');

        $this->client->request('GET', $loc2);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(), 'English profile page should load after revert.');

        $this->em()->refresh($member);
        $this->assertSame('en', $member->getLocale(), 'Member locale should revert back to en.');
    }
}
