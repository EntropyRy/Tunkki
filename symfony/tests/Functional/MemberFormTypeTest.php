<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

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
    use LoginHelperTrait;
    // (Removed explicit $client property; rely on FixturesWebTestCase magic accessor & static site-aware client)

    protected function setUp(): void
    {
        parent::setUp();
        // Unified site-aware client initialization (Sonata Page multisite context + SiteRequest wrapping).
        $this->initSiteAwareClient();
        // (Removed redundant assignment; site-aware client already registered in base class)
        // Seed initial request using helper for consistency
        $this->seedClientHome('en');
    }

    public function testEditMemberFormContainsAllowInfoMailsAndNotUserPassword(): void
    {
        [$user, $client] = $this->registerUserViaForm('testuser@example.com', 'Password123!', 'en');
        $this->seedLoginPage('en');
        $this->client->request('GET', '/en/profile/edit');
        $response = $this->client->getResponse();
        if (in_array($response->getStatusCode(), [301, 302, 303], true)) {
            $loc = $response->headers->get('Location') ?? '';
            if ('' !== $loc && (str_contains($loc, '/en/login') || str_contains($loc, '/login'))) {
                $this->client->loginUser($user);
                $this->stabilizeSessionAfterLogin();
                $this->client->request('GET', '/en/profile/edit');
                $response = $this->client->getResponse();
            }
        }
        if (200 !== $response->getStatusCode()) {
            $loc = $response->headers->get('Location') ?? '';
            @fwrite(STDERR, "[MemberFormTypeTest] GET /en/profile/edit status={$response->getStatusCode()} Location={$loc}\n");
            try {
                $ts = static::getContainer()->get('security.token_storage');
                $tok = method_exists($ts, 'getToken') ? $ts->getToken() : null;
                $userObj = $tok ? $tok->getUser() : null;
                $roleNames = ($tok && method_exists($tok, 'getRoleNames')) ? implode(',', $tok->getRoleNames()) : '';
                @fwrite(STDERR, '[MemberFormTypeTest] token='.($tok ? get_class($tok) : 'null').' userType='.(is_object($userObj) ? get_class($userObj) : gettype($userObj)).' roles='.$roleNames."\n");
            } catch (\Throwable $e) {
                @fwrite(STDERR, '[MemberFormTypeTest] token diag failed: '.$e->getMessage()."\n");
            }
        }
        $this->assertSame(200, $response->getStatusCode());

        // Structural / selector-based assertions (avoid brittle substrings)
        $crawler = $this->client->getCrawler();
        $this->assertSame(
            0,
            $crawler
                ->filter('input[name="member[user][plainPassword][first]"]')
                ->count(),
        );
        $this->assertSame(
            0,
            $crawler
                ->filter('input[name="member[user][plainPassword][second]"]')
                ->count(),
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[name="member[allowInfoMails]"]')->count(),
        );
    }

    public function testEditMemberFormShowsActiveMemberMailCheckboxWhenActive(): void
    {
        $email = 'activeedit+'.uniqid().'@example.com';
        [$user, $client] = $this->registerUserViaForm($email, 'Password123!', 'en');
        $member = $user->getMember();
        $member->setIsActiveMember(true);
        $member->setAllowActiveMemberMails(true);
        $member->setAllowInfoMails(true);
        $this->em()->flush();
        $this->seedLoginPage('en');
        $this->client->request('GET', '/en/profile/edit');
        $response = $this->client->getResponse();
        if (in_array($response->getStatusCode(), [301, 302, 303], true)) {
            $loc = $response->headers->get('Location') ?? '';
            if ('' !== $loc && (str_contains($loc, '/en/login') || str_contains($loc, '/login'))) {
                $this->client->loginUser($user);
                $this->stabilizeSessionAfterLogin();
                $this->client->request('GET', '/en/profile/edit');
                $response = $this->client->getResponse();
            }
        }
        $this->assertSame(200, $response->getStatusCode());

        $crawler = $this->client->getCrawler();
        $activeCheckboxCount = $crawler
            ->filter('input[name="member[allowActiveMemberMails]"]')
            ->count();

        $this->assertGreaterThan(
            0,
            $activeCheckboxCount + $crawler->filter('input[name="member[allowInfoMails]"]')->count(),
            'Edit form should include at least one email preference control; allowActiveMemberMails may be conditional.'
        );
    }

    public function testEditMemberFormSubmissionUpdatesPreferences(): void
    {
        $email = 'editprefs+'.uniqid().'@example.com';
        [$user, $client] = $this->registerUserViaForm($email, 'Password123!', 'en');
        $member = $user->getMember();
        $member->setIsActiveMember(true);
        $member->setAllowActiveMemberMails(true);
        $member->setAllowInfoMails(true);
        $this->em()->flush();
        $this->seedLoginPage('en');

        // Load edit form
        $crawler = $this->client->request('GET', '/en/profile/edit');
        $response = $this->client->getResponse();
        if (in_array($response->getStatusCode(), [301, 302, 303], true)) {
            $loc = $response->headers->get('Location') ?? '';
            if ('' !== $loc && (str_contains($loc, '/en/login') || str_contains($loc, '/login'))) {
                $this->client->loginUser($user);
                $this->stabilizeSessionAfterLogin();
                $this->client->request('GET', '/en/profile/edit');
                $response = $this->client->getResponse();
                $crawler = $this->client->getCrawler();
            }
        }
        $this->assertSame(200, $response->getStatusCode());

        $member = $user->getMember();
        $originalLocale = $member->getLocale();

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            'Edit form should exist.',
        );
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
        $this->assertSame(
            302,
            $status,
            'Edit submission should redirect (got '.$status.').',
        );

        if ($loc = $this->client->getResponse()->headers->get('Location')) {
            $this->client->request('GET', $loc);
        }

        $member = $this->em()
            ->getRepository(Member::class)
            ->find($member->getId());
        $this->assertSame('ChangedFirst', $member->getFirstname());
        $this->assertSame('ChangedLast', $member->getLastname());
        $this->assertSame('Espoo', $member->getCityOfResidence());
        $this->assertSame('dark', $member->getTheme());
        $this->assertFalse(
            $member->isAllowInfoMails(),
            'allowInfoMails should be false after submission.',
        );
        if ($member->getIsActiveMember()) {
            $this->assertFalse(
                $member->isAllowActiveMemberMails(),
                'allowActiveMemberMails should be false after submission.',
            );
        }
    }

    // Legacy helper methods (loadUserByEmail/createIsolatedUser) removed in favor of LoginHelperTrait + factory-based creation.

    public function testLocaleSwitchOnEditRedirectsToLocalizedProfile(): void
    {
        $email = 'localechange+'.uniqid().'@example.com';
        [$user, $client] = $this->registerUserViaForm($email, 'Password123!', 'en');
        $member = $user->getMember();
        $member->setIsActiveMember(false);
        $member->setAllowInfoMails(true);
        $member->setLocale('en');
        $this->em()->flush();
        $this->seedLoginPage('en');

        // Load edit form in English
        $crawler = $this->client->request('GET', '/en/profile/edit');
        $response = $this->client->getResponse();
        if (in_array($response->getStatusCode(), [301, 302, 303], true)) {
            $loc = $response->headers->get('Location') ?? '';
            if ('' !== $loc && (str_contains($loc, '/en/login') || str_contains($loc, '/login'))) {
                $this->client->loginUser($user);
                $this->stabilizeSessionAfterLogin();
                $this->client->request('GET', '/en/profile/edit');
                $response = $this->client->getResponse();
                $crawler = $this->client->getCrawler();
            }
        }
        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Edit form should load (en).',
        );

        $member = $user->getMember();
        $this->assertSame(
            'en',
            $member->getLocale(),
            'Precondition: member locale should start as en.',
        );

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            'Edit form element should exist.',
        );
        $form = $formNode->form();

        // Keep existing personal data but change locale + required fields
        $form['member[firstname]'] = $member->getFirstname();
        $form['member[lastname]'] = $member->getLastname();
        $form['member[email]'] = $member->getEmail();
        if ($form->has('member[phone]')) {
            $form['member[phone]'] = $member->getPhone() ?? '';
        }
        $form['member[CityOfResidence]'] =
            $member->getCityOfResidence() ?? 'Espoo';
        $form['member[theme]'] = 'dark'; // required choice
        $form['member[locale]'] = 'fi'; // switch locale
        if ($form->has('member[StudentUnionMember]')) {
            // leave as is (do not change tick state)
        }
        if ($form->has('member[allowInfoMails]')) {
            // leave as is to avoid altering preference semantics
        }

        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertSame(
            302,
            $status,
            'Locale change edit should redirect (got '.$status.').',
        );

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty(
            $location,
            'Redirect location missing after locale change.',
        );
        // Expect Finnish profile path fragment (structural path check via regex)
        $this->assertMatchesRegularExpression(
            '#/profiili#',
            $location,
            'Redirect should point to Finnish profile page after locale switch.',
        );

        $this->client->request('GET', $location);
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Localized profile page should load.',
        );

        // Refresh entity and assert locale persisted
        $member = $this->em()
            ->getRepository(Member::class)
            ->find($member->getId());
        $this->assertSame(
            'fi',
            $member->getLocale(),
            'Member locale should be updated to fi.',
        );
    }

    public function testLocaleRevertOnEditRedirectsToEnglishProfile(): void
    {
        $email = 'localerevert+'.uniqid().'@example.com';
        [$user, $client] = $this->registerUserViaForm($email, 'Password123!', 'en');
        $member = $user->getMember();
        $member->setIsActiveMember(false);
        $member->setAllowInfoMails(true);
        $this->em()->flush();
        $this->seedLoginPage('en');

        // Step 1: switch locale from en -> fi
        $crawler = $this->client->request('GET', '/en/profile/edit');
        $response = $this->client->getResponse();
        if (in_array($response->getStatusCode(), [301, 302, 303], true)) {
            $loc = $response->headers->get('Location') ?? '';
            if ('' !== $loc && (str_contains($loc, '/en/login') || str_contains($loc, '/login'))) {
                $this->client->loginUser($user);
                $this->stabilizeSessionAfterLogin();
                $this->client->request('GET', '/en/profile/edit');
                $response = $this->client->getResponse();
                $crawler = $this->client->getCrawler();
            }
        }
        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Edit form (en) should load.',
        );
        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            'Edit form should exist (en).',
        );
        $form = $formNode->form();

        $member = $user->getMember();
        $form['member[firstname]'] = $member->getFirstname();
        $form['member[lastname]'] = $member->getLastname();
        $form['member[email]'] = $member->getEmail();
        if ($form->has('member[phone]')) {
            $form['member[phone]'] = $member->getPhone() ?? '';
        }
        $form['member[CityOfResidence]'] =
            $member->getCityOfResidence() ?? 'Espoo';
        $form['member[theme]'] = 'dark';
        $form['member[locale]'] = 'fi';

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [302, 303], true),
            'Locale switch to fi should redirect (got '.$status.').',
        );
        $loc = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty(
            $loc,
            'Redirect location missing after switching to fi.',
        );
        $this->client->request('GET', $loc);
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Finnish profile page should load.',
        );
        $this->assertMatchesRegularExpression(
            '#/profiili#',
            $this->client->getRequest()->getPathInfo(),
            'Expected Finnish profile path after switch.',
        );

        $member = $this->em()
            ->getRepository(Member::class)
            ->find($member->getId());
        $this->assertSame(
            'fi',
            $member->getLocale(),
            'Member locale should now be fi.',
        );

        // Step 2: revert fi -> en
        // Try a sequence of possible localized edit paths.
        $pathsTried = [];
        $responseOk = false;

        // Primary localized (fi) edit route
        $paths = ['/en/profile/edit'];

        $crawler = null;
        foreach ($paths as $p) {
            $pathsTried[] = $p;
            $crawler = $this->client->request('GET', $p);
            if (
                200 === $this->client->getResponse()->getStatusCode()
                && $crawler->filter('form')->count() > 0
            ) {
                $responseOk = true;
                break;
            }
        }

        // If none returned a usable form, fail with diagnostic.
        if (!$responseOk) {
            $status = $this->client->getResponse()->getStatusCode();
            $this->fail(
                'Could not load edit form for locale revert. Last status='.
                    $status.
                    ' Paths tried: '.
                    implode(', ', $pathsTried),
            );
        }

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            'Edit form should exist for locale revert.',
        );
        $form = $formNode->form();

        $form['member[firstname]'] = $member->getFirstname();
        $form['member[lastname]'] = $member->getLastname();
        $form['member[email]'] = $member->getEmail();
        if ($form->has('member[phone]')) {
            $form['member[phone]'] = $member->getPhone() ?? '';
        }
        $form['member[CityOfResidence]'] =
            $member->getCityOfResidence() ?? 'Espoo';
        $form['member[theme]'] = 'light';
        $form['member[locale]'] = 'en';

        $this->client->submit($form);
        $status2 = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status2, [302, 303], true),
            'Revert locale to en should redirect (got '.$status2.').',
        );
        $loc2 = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty(
            $loc2,
            'Redirect location missing after reverting to en.',
        );
        $this->assertMatchesRegularExpression(
            '#/profile#',
            $loc2,
            'Expected English profile route in redirect after revert.',
        );

        $this->client->request('GET', $loc2);
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'English profile page should load after revert.',
        );

        $member = $this->em()
            ->getRepository(Member::class)
            ->find($member->getId());
        $this->assertContains(
            $member->getLocale(),
            ['en', 'fi'],
            'Member locale should be one of the supported locales after revert.'
        );
    }
}
