<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\RSVP;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Twig\Components\EventRsvp;
use PHPUnit\Framework\Attributes\DataProvider;

final class EventRsvpTest extends LiveComponentTestCase
{
    #[DataProvider('localeProvider')]
    public function testAnonymousRendersButtonAndHiddenFormUntilOpened(
        string $locale,
        string $expectedJoinHref,
    ): void {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], $locale);

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('button[data-live-action-param="openForm"]')->count());
        self::assertSame(0, $crawler->filter('form.rsvp-form')->count());
        self::assertSame(0, $crawler->filter(\sprintf('a[href="%s"]', $expectedJoinHref))->count());

        $component->call('openForm');

        $crawler = $component->render()->crawler();
        self::assertSame(0, $crawler->filter('button[data-live-action-param="openForm"]')->count());
        self::assertSame(1, $crawler->filter('form.rsvp-form')->count());
        self::assertSame(1, $crawler->filter(\sprintf('a[href="%s"]', $expectedJoinHref))->count());

        $emailInput = $crawler->filter('input[name="rsvp[email]"]');
        self::assertSame(1, $emailInput->count());
        self::assertSame('on(change)|rsvp.email', $emailInput->attr('data-model'));
    }

    public function testAnonymousInvalidEmailAddsIsInvalidAndReturns422(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], 'en');

        $component->call('openForm');

        $this->client()->catchExceptions(true);
        try {
            $response = $component
                ->submitForm([
                    'rsvp' => [
                        'firstName' => 'Ada',
                        'lastName' => 'Lovelace',
                        'email' => 'not-an-email',
                    ],
                ], 'saveAnonymous')
                ->response();
        } finally {
            $this->client()->catchExceptions(false);
        }

        self::assertSame(422, $response->getStatusCode());

        $crawler = $component->render()->crawler();
        $emailInput = $crawler->filter('input[name="rsvp[email]"]');
        self::assertSame(1, $emailInput->count());
        $classes = preg_split('/\\s+/', trim((string) $emailInput->attr('class'))) ?: [];
        self::assertContains('is-invalid', $classes);
    }

    public function testAnonymousValidSubmissionPersistsRsvpAndShowsSuccess(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $eventId = $event->getId();
        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], 'en');

        $component->call('openForm');

        $email = 'rsvp+'.uniqid('', true).'@example.com';
        $response = $component
            ->submitForm([
                'rsvp' => [
                    'firstName' => 'Test',
                    'lastName' => 'User',
                    'email' => $email,
                ],
            ], 'saveAnonymous')
            ->response();

        self::assertSame(200, $response->getStatusCode());

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-success')->count());
        self::assertSame(1, $crawler->filter('button[data-live-action-param="openForm"]')->count());

        $this->refreshEntityManager();
        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $eventId,
            'email' => $email,
        ]);
        self::assertCount(1, $rsvps);
    }

    public function testCloseFormHidesFormAndClearsValidationState(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], 'en');

        $component->call('openForm');

        $this->client()->catchExceptions(true);
        try {
            $component->submitForm([
                'rsvp' => [
                    'firstName' => 'Ada',
                    'lastName' => 'Lovelace',
                    'email' => 'not-an-email',
                ],
            ], 'saveAnonymous');
        } finally {
            $this->client()->catchExceptions(false);
        }

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('input[name="rsvp[email]"].is-invalid')->count());

        $component->call('closeForm');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('button[data-live-action-param="openForm"]')->count());
        self::assertSame(0, $crawler->filter('form.rsvp-form')->count());
        self::assertSame(0, $crawler->filter('.alert')->count());
    }

    public function testHasRsvpdReturnsFalseForAnonymous(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
            'member' => null,
        ], 'en');

        /** @var EventRsvp $state */
        $state = $component->component();
        self::assertFalse($state->hasRsvpd());
    }

    public function testAnonymousSaveShowsEmailInUseAndDoesNotPersistWhenMemberExists(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        MemberFactory::new()->inactive()->create([
            'email' => 'existing@example.com',
        ]);

        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], 'en');

        $component->call('openForm');
        $component->refresh();
        $component->submitForm([
            'rsvp' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'email' => 'existing@example.com',
            ],
        ], 'saveAnonymous');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-warning')->count());

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'email' => 'existing@example.com',
        ]);
        self::assertCount(0, $rsvps);
    }

    public function testRsvpAsMemberWhenAlreadyRsvpdShowsWarningAndDoesNotPersistSecondTime(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $eventId = $event->getId();
        $member = MemberFactory::new()->inactive()->english()->create();
        $memberId = $member->getId();

        $existing = new RSVP();
        $existing->setEvent($event);
        $existing->setMember($member);
        $this->em()->persist($existing);
        $this->em()->flush();

        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
            'member' => $member,
        ], 'en');

        $component->call('rsvpAsMember');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-warning')->count());

        $this->refreshEntityManager();
        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $eventId,
            'member' => $memberId,
        ]);
        self::assertCount(1, $rsvps);
    }

    public function testRsvpAsMemberWithoutUserShowsNoUserErrorAndDoesNotPersist(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], 'en');

        $component->call('rsvpAsMember');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-warning')->count());

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
        ]);
        self::assertCount(0, $rsvps);
    }

    public function testOpenFormDoesNothingWhenMemberProvided(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $member = MemberFactory::new()->inactive()->english()->create();

        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
            'member' => $member,
        ], 'en');

        $component->call('openForm');

        /** @var EventRsvp $state */
        $state = $component->component();
        self::assertFalse($state->formOpen);
    }

    public function testActiveMemberRsvpShowsCountAndPreventsDuplicate(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();
        $member = MemberFactory::new()->active()->english()->create();

        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
            'member' => $member,
        ], 'en');

        $crawler = $component->render()->crawler();
        $button = $crawler->filter('form[data-live-action-param="rsvpAsMember"] button[type="submit"]');
        self::assertSame(1, $button->count());
        self::assertSame('RSVP (0)', trim($button->text()));

        $component->call('rsvpAsMember');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-success')->count());

        $component->call('rsvpAsMember');

        $crawler = $component->render()->crawler();
        self::assertSame(1, $crawler->filter('.alert.alert-warning')->count());
        $disabled = $crawler->filter('button[disabled][aria-disabled="true"]');
        self::assertSame(1, $disabled->count());
        self::assertSame(
            '✓ You have already RSVPed to this event. Total shown only to active members: 1',
            preg_replace('/\\s+/', ' ', trim($disabled->text())),
        );
    }

    public function testComponentNotVisibleForPastEvent(): void
    {
        $event = EventFactory::new()->finished()->withRsvpEnabled()->create();
        $component = $this->mountComponent(EventRsvp::class, [
            'event' => $event,
        ], 'en');

        self::assertSame('', trim($component->render()->toString()));
    }

    public static function localeProvider(): array
    {
        return [
            ['fi', '/liity'],
            ['en', '/en/join-us'],
        ];
    }
}
