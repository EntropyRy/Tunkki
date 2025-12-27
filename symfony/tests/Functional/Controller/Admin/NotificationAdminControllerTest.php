<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\Notification;
use App\Entity\Sonata\SonataMediaMedia;
use App\Factory\EventFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use App\Tests\Support\Notifier\TestChatter;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Group;
use Sonata\MediaBundle\Provider\MediaProviderInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Test\Factories;

#[Group('admin')]
#[Group('notification')]
final class NotificationAdminControllerTest extends FixturesWebTestCase
{
    use Factories;
    use LoginHelperTrait;

    private ObjectManager $entityManager;

    private function uniqueAdminEmail(): string
    {
        return 'admin-'.uniqid('', true).'@example.com';
    }

    private function getTestChatter(): TestChatter
    {
        $chatter = static::getContainer()->get(ChatterInterface::class);
        $this->assertInstanceOf(TestChatter::class, $chatter);
        $chatter->reset();

        return $chatter;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureClientReady();
        $this->client->request('GET', '/admin/dashboard');
        $this->entityManager = $this->em();
    }

    public function testSendActionRedirectsAnonymousUserToLogin(): void
    {
        $event = EventFactory::new()->published()->create();
        $notification = $this->createNotification($event, 'fi', '<b>Hello</b>', []);

        $this->client->request('GET', '/admin/notification/list');

        $response = $this->client->getResponse();
        $status = $response->getStatusCode();

        $this->assertTrue(
            \in_array($status, [302, 303], true),
            \sprintf('Expected redirect to login for anonymous admin list, got %d.', $status),
        );
        $this->assertStringContainsString('/login', $response->headers->get('Location') ?? '');
    }

    public function testSendActionDeniesNonAdminUser(): void
    {
        $event = EventFactory::new()->published()->create();
        $notification = $this->createNotification($event, 'fi', '<b>Hello</b>', []);
        [$_user, $_client] = $this->loginAsEmail('regular@example.com');

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSendActionSendsMessageAndUpdatesSentAt(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'fi', '<b>Hello</b>', [
            'add_event_button',
            'add_shop_button',
            'add_nakkikone_button',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Notification::class)->find($notification->getId());
        $this->assertInstanceOf(Notification::class, $reloaded);
        $this->assertNotNull($reloaded->getSentAt());

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $this->assertSame('<b>Hello</b>', $message->getSubject());

        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);
        $optionsArray = $options->toArray();
        $this->assertSame('HTML', $optionsArray['parse_mode'] ?? null);
        $this->assertTrue($optionsArray['disable_notification'] ?? null);
        $this->assertArrayHasKey('reply_markup', $optionsArray);
        $inlineKeyboard = $optionsArray['reply_markup']['inline_keyboard'] ?? null;
        $this->assertIsArray($inlineKeyboard);
        $this->assertCount(1, $inlineKeyboard);
        $this->assertCount(3, $inlineKeyboard[0]);

        $urls = array_map(static fn (array $button): ?string => $button['url'] ?? null, $inlineKeyboard[0]);
        $this->assertContains('http://localhost/tapahtuma/'.$event->getId().'?source=tg', $urls);
        $this->assertContains('http://localhost/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/kauppa?source=tg', $urls);
        $this->assertContains('http://localhost/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/nakkikone?source=tg', $urls);
    }

    public function testSendActionButtonsCanBeConfiguredToOnePerRow(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'fi', 'Hello', [
            'buttons_one_per_row',
            'add_event_button',
            'add_shop_button',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);

        $inlineKeyboard = $options->toArray()['reply_markup']['inline_keyboard'] ?? null;
        $this->assertIsArray($inlineKeyboard);
        $this->assertCount(2, $inlineKeyboard);
        $this->assertCount(1, $inlineKeyboard[0]);
        $this->assertCount(1, $inlineKeyboard[1]);
    }

    public function testSendActionBuildsEnglishLinksUnderEnPrefix(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'en', 'Hello', [
            'add_event_button',
            'add_shop_button',
            'add_nakkikone_button',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());
        $this->seedClientHome('en');

        $this->client->request('GET', "/en/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);

        $inlineKeyboard = $options->toArray()['reply_markup']['inline_keyboard'] ?? null;
        $this->assertIsArray($inlineKeyboard);
        $this->assertCount(1, $inlineKeyboard);
        $this->assertCount(3, $inlineKeyboard[0]);

        $urls = array_map(static fn (array $button): ?string => $button['url'] ?? null, $inlineKeyboard[0]);
        $this->assertContains('http://localhost/en/event/'.$event->getId().'?source=tg', $urls);
        $this->assertContains('http://localhost/en/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/shop?source=tg', $urls);
        $this->assertContains('http://localhost/en/'.$event->getEventDate()->format('Y').'/'.$event->getUrl().'/nakkikone?source=tg', $urls);
    }

    public function testSendActionAppliesOptionToggles(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'fi', 'Hello', [
            'add_preview_link',
            'send_notification',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);

        $optionsArray = $options->toArray();
        $this->assertFalse($optionsArray['disable_web_page_preview'] ?? true);
        $this->assertFalse($optionsArray['disable_notification'] ?? true);
    }

    public function testSendActionWithNullMessageStillSends(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'fi', null, []);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $this->assertSame('', $message->getSubject());
    }

    public function testSendActionInDevCanUseDebugPictureForPhotoOption(): void
    {
        $prevEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        try {
            $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
            $notification = $this->createNotification($event, 'fi', 'Hello', [
                'add_event_picture',
            ]);

            $fakeChatter = $this->getTestChatter();

            [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

            $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
            $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

            $this->assertCount(1, $fakeChatter->messages);
            $message = $fakeChatter->messages[0];
            $options = $message->getOptions();
            $this->assertInstanceOf(TelegramOptions::class, $options);

            $this->assertSame(
                'https://entropy.fi/upload/media/event/0001/01/c9ae350d6d50efeadd95eab3270604a78719fb1b.jpg',
                $options->toArray()['photo'] ?? null,
            );
        } finally {
            if (null === $prevEnv) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $prevEnv;
            }
        }
    }

    public function testSendActionDoesNotAttemptToEditExistingMessages(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'fi', 'Hello', [
            'add_event_picture',
        ]);
        $notification->setMessageId(123);
        $this->entityManager->flush();

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);

        $this->assertArrayNotHasKey('message_id', $options->toArray());
    }

    public function testSendActionWithVenueAddsTelegramVenuePayload(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $location = $this->createLocation();
        $event->setLocation($location);
        $this->entityManager->flush();

        $notification = $this->createNotification($event, 'fi', 'Hello', [
            'add_venue',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);

        $venue = $options->toArray()['venue'] ?? null;
        $this->assertIsArray($venue);
        $this->assertSame(60.123, $venue['latitude'] ?? null);
        $this->assertSame(24.456, $venue['longitude'] ?? null);
        $this->assertSame($event->getName().' @ '.$location->getNameByLocale('fi'), $venue['title'] ?? null);
        $this->assertSame($location->getStreetAddress(), $venue['address'] ?? null);
    }

    public function testSendActionWithVenueOptionButMissingLocationShowsWarningAndDoesNotSend(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $event->setLocation(null);
        $this->entityManager->flush();

        $notification = $this->createNotification($event, 'fi', 'Hello', [
            'add_venue',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert.alert-warning');
        $this->client->assertSelectorTextContains('.alert.alert-warning', 'Cannot add venue');

        $this->assertCount(0, $fakeChatter->messages);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Notification::class)->find($notification->getId());
        $this->assertInstanceOf(Notification::class, $reloaded);
        $this->assertNull($reloaded->getSentAt());
    }

    public function testSendActionWithEventPictureUsesMediaProviderPublicUrlInTestEnv(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $pool = static::getContainer()->get(Pool::class);
        $provider = $this->createStub(MediaProviderInterface::class);
        $provider->method('getFormatName')->willReturn('reference');
        $provider->method('generatePublicUrl')->willReturn('/generated/test.jpg');
        $pool->addProvider('test.provider', $provider);

        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $picture = $this->createEventPicture('test.provider');
        $event->setPicture($picture);
        $this->entityManager->flush();

        $notification = $this->createNotification($event, 'fi', 'Hello', [
            'add_event_picture',
        ]);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(1, $fakeChatter->messages);
        $message = $fakeChatter->messages[0];
        $options = $message->getOptions();
        $this->assertInstanceOf(TelegramOptions::class, $options);

        $this->assertSame('http://localhost/generated/test.jpg', $options->toArray()['photo'] ?? null);
    }

    public function testSendActionWithMissingEventRedirectsAndDoesNotSend(): void
    {
        $notification = $this->createNotification(null, 'fi', 'Hello', []);

        $fakeChatter = $this->getTestChatter();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->assertCount(0, $fakeChatter->messages);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Notification::class)->find($notification->getId());
        $this->assertInstanceOf(Notification::class, $reloaded);
        $this->assertNull($reloaded->getSentAt());
    }

    public function testSendActionShowsWarningWhenChatterThrowsAndDoesNotSetSentAt(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $notification = $this->createNotification($event, 'fi', 'Hello', []);

        $throwingChatter = $this->getTestChatter();
        $throwingChatter->setShouldThrow(true);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN', [], $this->uniqueAdminEmail());

        $this->client->request('GET', "/admin/notification/{$notification->getId()}/send");
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert.alert-warning');
        $this->client->assertSelectorTextContains('.alert.alert-warning', 'Message NOT sent');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Notification::class)->find($notification->getId());
        $this->assertInstanceOf(Notification::class, $reloaded);
        $this->assertNull($reloaded->getSentAt());
    }

    private function createNotification(Event|Proxy|null $event, string $locale, ?string $message, array $options): Notification
    {
        $notification = new Notification();
        $eventEntity = $event instanceof Proxy ? $event->object() : $event;
        $notification->setEvent($eventEntity);
        $notification->setLocale($locale);
        $notification->setMessage($message);
        $notification->setOptions($options);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->assertNotNull($notification->getId());

        return $notification;
    }

    private function createLocation(): Location
    {
        $location = new Location();
        $location->setName('Test Venue');
        $location->setNameEn('Test Venue');
        $location->setLatitude('60.123');
        $location->setLongitude('24.456');
        $location->setStreetAddress('Testikatu 1');

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        $this->assertNotNull($location->getId());

        return $location;
    }

    private function createEventPicture(string $providerName): SonataMediaMedia
    {
        $media = new SonataMediaMedia();
        $media->setName('Test picture');
        $media->setEnabled(true);
        $media->setProviderName($providerName);
        $media->setProviderStatus(1);
        $media->setProviderReference('ref-test');
        $media->setContext('event');

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        $this->assertNotNull($media->getId());

        return $media;
    }
}
