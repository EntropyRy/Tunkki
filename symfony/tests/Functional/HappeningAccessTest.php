<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Happening;
use App\Factory\EventFactory;
use App\Factory\HappeningFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Http\SiteAwareKernelBrowser;
use App\Tests\Support\LoginHelperTrait;

/**
 * Fixture-free functional tests verifying Happening visibility & interactions.
 *
 * Scenario Coverage:
 *  - Public (released) happening visible to its owner and another authenticated user.
 *  - Private (unreleased) happening visible only to its owner (404 to others).
 *  - Owner can access creation form & create a minimal happening.
 *  - Non-owner cannot edit someone else's happening (redirect or missing form).
 *  - Another user can book a released happening.
 *
 * Policy (Decision 2025-10-03 – Fixture-Free Suite):
 *  No reliance on pre-loaded Doctrine fixtures. All data created inline via factories.
 *
 * NOTE: Routes require authentication (controller #[IsGranted]).
 */
final class HappeningAccessTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testPublicHappeningAccessibleToOwnerAndAnotherUser(): void
    {
        $owner = $this->getOrCreateUser(
            sprintf(
                'happening-owner+%s@example.test',
                bin2hex(random_bytes(4)),
            ),
            [],
        );
        $other = $this->getOrCreateUser(
            sprintf(
                'happening-other+%s@example.test',
                bin2hex(random_bytes(4)),
            ),
            [],
        );

        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->withOwner($owner)
            ->create();

        $year = $event->getEventDate()->format('Y');

        // Owner access
        $this->client->loginUser($owner);
        $this->stabilizeSessionAfterLogin();
        $this->client->request(
            'GET',
            sprintf(
                '/en/%s/%s/happening/%s',
                $year,
                $event->getUrl(),
                $happening->getSlugEn(),
            ),
        );
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Owner should access released happening.',
        );

        // Another authenticated user
        $this->client->loginUser($other);
        $this->stabilizeSessionAfterLogin();
        $this->client->request(
            'GET',
            sprintf(
                '/en/%s/%s/happening/%s',
                $year,
                $event->getUrl(),
                $happening->getSlugEn(),
            ),
        );
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Other authenticated user should access released happening.',
        );
    }

    public function testPrivateHappeningOnlyAccessibleToOwner(): void
    {
        $owner = $this->getOrCreateUser(
            sprintf('private-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $stranger = $this->getOrCreateUser(
            sprintf(
                'private-stranger+%s@example.test',
                bin2hex(random_bytes(4)),
            ),
            [],
        );

        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->unreleased()
            ->forEvent($event)
            ->withOwner($owner)
            ->create();

        $year = $event->getEventDate()->format('Y');

        // Owner can access
        $this->client->loginUser($owner);
        $this->stabilizeSessionAfterLogin();
        $this->client->request(
            'GET',
            sprintf(
                '/en/%s/%s/happening/%s',
                $year,
                $event->getUrl(),
                $happening->getSlugEn(),
            ),
        );
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Owner should access private happening.',
        );

        // Stranger gets 404
        $this->client->loginUser($stranger);
        $this->stabilizeSessionAfterLogin();
        $this->client->request(
            'GET',
            sprintf(
                '/en/%s/%s/happening/%s',
                $year,
                $event->getUrl(),
                $happening->getSlugEn(),
            ),
        );
        $this->assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            'Stranger should not access private happening.',
        );
    }

    public function testOwnerCanAccessCreateFormAndSubmitMinimalHappening(): void
    {
        $owner = $this->getOrCreateUser(
            sprintf('creator-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $event = EventFactory::new()->published()->create();
        $year = $event->getEventDate()->format('Y');

        $this->client->loginUser($owner);
        $this->stabilizeSessionAfterLogin();

        $createUrl = sprintf(
            '/en/%s/%s/happening/create',
            $year,
            $event->getUrl(),
        );
        $crawler = $this->client->request('GET', $createUrl);
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Create form should load.',
        );

        $formNode = $crawler->filter('form')->first();
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            'Creation form missing.',
        );
        $form = $formNode->form();
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);

        $form['happening[type]'] = 'event';
        $form['happening[nameFi]'] = 'Luotu FI '.$suffix;
        $form['happening[descriptionFi]'] = 'Kuvaus FI';
        $form['happening[nameEn]'] = 'Created EN '.$suffix;
        $form['happening[descriptionEn]'] = 'Description EN';
        $this->setCheckboxState($form, 'happening[releaseThisHappeningInEvent]', true);
        $this->setCheckboxState($form, 'happening[allowSignUpComments]', true);
        $form['happening[maxSignUps]'] = '0';

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        $errorInfo = '';
        if (!in_array($status, [302, 303], true)) {
            $crawlerAfter = new \Symfony\Component\DomCrawler\Crawler($this->client->getResponse()->getContent() ?? '');
            $errs = $crawlerAfter->filter('.invalid-feedback, .form-error-message, form ul li')->each(
                static fn (\Symfony\Component\DomCrawler\Crawler $n) => trim($n->text())
            );
            $errs = array_values(array_filter($errs, static fn (string $t) => '' !== $t));
            if (!empty($errs)) {
                $errorInfo = ' Errors: '.implode(' | ', $errs);
            }
        }
        $bodySnippet = substr(trim(strip_tags($this->client->getResponse()->getContent() ?? '')), 0, 600);
        $this->assertTrue(
            in_array($status, [302, 303], true),
            sprintf(
                'Creation should redirect. HTTP %d.%s Snippet: %s',
                $status,
                $errorInfo,
                $bodySnippet
            ),
        );

        if ($loc = $this->client->getResponse()->headers->get('Location')) {
            $this->client->request('GET', $loc);
            $this->assertSame(
                200,
                $this->client->getResponse()->getStatusCode(),
                'Show page should load after redirect.',
            );
        }
    }

    public function testOwnerCanEditOwnHappeningAndNonOwnerCannot(): void
    {
        $owner = $this->getOrCreateUser(
            sprintf('edit-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $nonOwner = $this->getOrCreateUser(
            sprintf('edit-stranger+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );

        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->withOwner($owner)
            ->create();

        $year = $event->getEventDate()->format('Y');

        // Owner visits edit path
        $this->client->loginUser($owner);
        $this->stabilizeSessionAfterLogin();
        $editUrl = sprintf(
            '/en/%s/%s/happening/%s/edit',
            $year,
            $event->getUrl(),
            $happening->getSlugEn(),
        );
        $crawler = $this->client->request('GET', $editUrl);
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Owner should access edit form.',
        );
        $this->assertGreaterThan(
            0,
            $crawler->filter('form')->count(),
            'Edit form expected for owner.',
        );

        // Non-owner attempt
        $this->client->loginUser($nonOwner);
        $this->stabilizeSessionAfterLogin();
        $this->client->request('GET', $editUrl);
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [200, 302, 303], true),
            'Non-owner should not hard error.',
        );
        if (200 === $status) {
            $this->assertStringNotContainsString(
                'name="happening[nameEn]"',
                $this->client->getResponse()->getContent() ?? '',
                'Non-owner should not see full edit form.',
            );
        }
    }

    public function testAnotherUserCanBookPublicHappening(): void
    {
        $owner = $this->getOrCreateUser(
            sprintf('booking-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $booker = $this->getOrCreateUser(
            sprintf('booking-user+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );

        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->needsSignUp()
            ->signUpsOpenWindow()
            ->forEvent($event)
            ->withOwner($owner)
            ->create();

        $year = $event->getEventDate()->format('Y');

        $this->client->loginUser($booker);
        $this->stabilizeSessionAfterLogin();
        $showUrl = sprintf(
            '/en/%s/%s/happening/%s',
            $year,
            $event->getUrl(),
            $happening->getSlugEn(),
        );
        $crawler = $this->client->request('GET', $showUrl);
        $this->assertSame(
            200,
            $this->client->getResponse()->getStatusCode(),
            'Show page should load for booking user.',
        );

        $formNode = $crawler->filter('form[name="happening_booking"]');
        if (0 === $formNode->count()) {
            $formNode = $crawler->filter('form')->first();
        }
        $this->assertGreaterThan(
            0,
            $formNode->count(),
            'Booking form should be present.',
        );

        $form = $formNode->form();
        if ($form->has('happening_booking[comment]')) {
            $form['happening_booking[comment]'] = 'Looking forward!';
        }
        $this->client->submit($form);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($status, [302, 303], true),
            'Booking should redirect.',
        );
    }

    /**
     * Helper: Log in current SiteAwareKernelBrowser client as email (create if missing).
     */
    private function loginExistingClientAs(string $email): \App\Entity\User
    {
        $user = $this->getOrCreateUser($email, []);
        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        return $user;
    }

    /**
     * Safely (un)check a checkbox field.
     */
    private function setCheckboxState(
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
}
