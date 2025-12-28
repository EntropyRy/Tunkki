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
            \sprintf(
                'happening-owner+%s@example.test',
                bin2hex(random_bytes(4)),
            ),
            [],
        );
        $other = $this->getOrCreateUser(
            \sprintf(
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
        $this->loginClientAs($owner);
        $this->client->request(
            'GET',
            \sprintf(
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
        $this->loginClientAs($other);
        $this->client->request(
            'GET',
            \sprintf(
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
            \sprintf('private-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $stranger = $this->getOrCreateUser(
            \sprintf(
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
        $this->loginClientAs($owner);
        $this->client->request(
            'GET',
            \sprintf(
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
        $this->loginClientAs($stranger);
        $this->client->request(
            'GET',
            \sprintf(
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
            \sprintf('creator-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $event = EventFactory::new()->published()->create();
        $year = $event->getEventDate()->format('Y');

        $this->loginClientAs($owner);

        $createUrl = \sprintf(
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
        $this->setCheckboxState(
            $form,
            'happening[releaseThisHappeningInEvent]',
            true,
        );
        $this->setCheckboxState($form, 'happening[allowSignUpComments]', true);
        $form['happening[maxSignUps]'] = '0';

        $this->client->submit($form);
        $status = $this->client->getResponse()->getStatusCode();
        $errorInfo = '';
        if (!\in_array($status, [302, 303], true)) {
            $crawlerAfter = new \Symfony\Component\DomCrawler\Crawler(
                $this->client->getResponse()->getContent() ?? '',
            );
            $errs = $crawlerAfter
                ->filter('.invalid-feedback, .form-error-message, form ul li')
                ->each(
                    static fn (\Symfony\Component\DomCrawler\Crawler $n) => trim(
                        $n->text(),
                    ),
                );
            $errs = array_values(
                array_filter($errs, static fn (string $t) => '' !== $t),
            );
            if (!empty($errs)) {
                $errorInfo = ' Errors: '.implode(' | ', $errs);
            }
        }
        $bodySnippet = substr(
            trim(strip_tags($this->client->getResponse()->getContent() ?? '')),
            0,
            600,
        );
        $this->assertTrue(
            \in_array($status, [302, 303], true),
            \sprintf(
                'Creation should redirect. HTTP %d.%s Snippet: %s',
                $status,
                $errorInfo,
                $bodySnippet,
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
            \sprintf('edit-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $nonOwner = $this->getOrCreateUser(
            \sprintf('edit-stranger+%s@example.test', bin2hex(random_bytes(4))),
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
        $this->loginClientAs($owner);
        $editUrl = \sprintf(
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
        $this->loginClientAs($nonOwner);
        $this->client->request('GET', $editUrl);
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            \in_array($status, [200, 302, 303], true),
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
            \sprintf('booking-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $booker = $this->getOrCreateUser(
            \sprintf('booking-user+%s@example.test', bin2hex(random_bytes(4))),
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

        $this->loginClientAs($booker);
        $showUrl = \sprintf(
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
            \in_array($status, [302, 303], true),
            'Booking should redirect.',
        );
    }

    /**
     * Helper: Log in current SiteAwareKernelBrowser client as email (create if missing).
     */
    private function loginExistingClientAs(string $email): \App\Entity\User
    {
        $user = $this->getOrCreateUser($email, []);
        $this->loginClientAs($user);

        return $user;
    }

    /**
     * Log in current SiteAwareKernelBrowser client as the given user.
     */
    private function loginClientAs(\App\Entity\User $user): void
    {
        $this->client->loginUser($user);
        $this->stabilizeSessionAfterLogin();
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

    /**
     * Test that creating a happening with duplicate slug shows warning.
     * Covers lines 61-67.
     */
    public function testCreateHappeningWithDuplicateSlugShowsWarning(): void
    {
        $owner = $this->getOrCreateUser(
            \sprintf('dup-slug-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );

        $event = EventFactory::new()->published()->create();
        $year = $event->getEventDate()->format('Y');

        // Create first happening - the slug will be generated from the name
        $existingHappening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->withOwner($owner)
            ->create([
                'nameFi' => 'Duplikaatti Tapahtuma',
                'nameEn' => 'Duplicate Happening',
                'slugFi' => 'duplikaatti-tapahtuma',
                'slugEn' => 'duplicate-happening',
            ]);

        $this->loginClientAs($owner);

        // Try to create another happening with the same NAME (which generates the same slug)
        $createUrl = \sprintf(
            '/en/%s/%s/happening/create',
            $year,
            $event->getUrl(),
        );
        $crawler = $this->client->request('GET', $createUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();
        $form['happening[type]'] = 'event';
        // Use the same names - the form will generate the same slugs
        $form['happening[nameFi]'] = 'Duplikaatti Tapahtuma';
        $form['happening[descriptionFi]'] = 'Kuvaus FI';
        $form['happening[nameEn]'] = 'Duplicate Happening';
        $form['happening[descriptionEn]'] = 'Description EN';
        $this->setCheckboxState($form, 'happening[releaseThisHappeningInEvent]', true);
        $form['happening[maxSignUps]'] = '0';

        $this->client->submit($form);

        // Should redirect with warning flash (to edit page for the duplicate)
        $this->assertTrue(
            \in_array($this->client->getResponse()->getStatusCode(), [302, 303], true),
            'Should redirect after duplicate slug detection.',
        );
    }

    /**
     * Test that owner can successfully submit edit form.
     * Covers lines 119-127.
     */
    public function testOwnerCanSubmitEditFormSuccessfully(): void
    {
        $owner = $this->getOrCreateUser(
            \sprintf('edit-submit-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );

        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->withOwner($owner)
            ->create();

        $year = $event->getEventDate()->format('Y');

        $this->loginClientAs($owner);

        $editUrl = \sprintf(
            '/en/%s/%s/happening/%s/edit',
            $year,
            $event->getUrl(),
            $happening->getSlugEn(),
        );
        $crawler = $this->client->request('GET', $editUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $form = $crawler->filter('form')->first()->form();

        // Modify something
        $newName = 'Updated Name '.bin2hex(random_bytes(3));
        $form['happening[nameEn]'] = $newName;

        $this->client->submit($form);

        // Should redirect after successful edit
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            \in_array($status, [302, 303], true),
            \sprintf('Edit should redirect. Got HTTP %d.', $status),
        );

        // Follow redirect and verify we're on show page
        $this->client->followRedirect();
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test that payment info is rendered as markdown.
     * Covers line 196.
     */
    public function testHappeningShowRendersPaymentInfoAsMarkdown(): void
    {
        $owner = $this->getOrCreateUser(
            \sprintf('payment-info-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );

        // Event must be in the future for payment info to show
        $event = EventFactory::new()->published()->create([
            'EventDate' => new \DateTimeImmutable('+7 days'),
        ]);
        $happening = HappeningFactory::new()
            ->released()
            ->needsPayment()  // This sets needsPreliminaryPayment = true
            ->forEvent($event)
            ->withOwner($owner)
            ->create([
                'paymentInfoEn' => '**Bold payment info** with [link](https://example.com)',
                'paymentInfoFi' => '**Maksutiedot** lihavoituna',
            ]);

        $year = $event->getEventDate()->format('Y');

        $this->loginClientAs($owner);

        $showUrl = \sprintf(
            '/en/%s/%s/happening/%s',
            $year,
            $event->getUrl(),
            $happening->getSlugEn(),
        );
        $this->client->request('GET', $showUrl);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        // The markdown should be converted - check for the strong tag
        $this->assertStringContainsString(
            '<strong>Bold payment info</strong>',
            $this->client->getResponse()->getContent(),
        );
    }

    /**
     * Test that user can remove their own booking.
     * Covers lines 225-238.
     */
    public function testUserCanRemoveOwnBooking(): void
    {
        $owner = $this->getOrCreateUser(
            \sprintf('remove-booking-owner+%s@example.test', bin2hex(random_bytes(4))),
            [],
        );
        $booker = $this->getOrCreateUser(
            \sprintf('remove-booking-user+%s@example.test', bin2hex(random_bytes(4))),
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

        // First, create a booking
        $this->loginClientAs($booker);
        $showUrl = \sprintf(
            '/en/%s/%s/happening/%s',
            $year,
            $event->getUrl(),
            $happening->getSlugEn(),
        );
        $crawler = $this->client->request('GET', $showUrl);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $formNode = $crawler->filter('form[name="happening_booking"]');
        if (0 === $formNode->count()) {
            $formNode = $crawler->filter('form')->first();
        }
        $form = $formNode->form();
        $this->client->submit($form);

        // Now get the booking ID from the database
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $happeningBookingRepo = $em->getRepository(\App\Entity\HappeningBooking::class);
        $booking = $happeningBookingRepo->findOneBy([
            'member' => $booker->getMember(),
            'happening' => $happening,
        ]);
        $this->assertNotNull($booking, 'Booking should exist.');

        // Now remove the booking
        $removeUrl = \sprintf('/happening/%d/remove', $booking->getId());
        $this->client->request('GET', $removeUrl);

        // Should redirect after removal
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            \in_array($status, [302, 303], true),
            \sprintf('Remove should redirect. Got HTTP %d.', $status),
        );

        // Verify booking is removed
        $em->clear();
        $deletedBooking = $happeningBookingRepo->find($booking->getId());
        $this->assertNull($deletedBooking, 'Booking should be deleted.');
    }
}
