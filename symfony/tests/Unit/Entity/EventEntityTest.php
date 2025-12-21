<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use PHPUnit\Framework\TestCase;

class EventEntityTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $event = new Event();

        // ID should be null before persistence
        $this->assertNull($event->getId());

        // Default values and nullables
        $this->assertSame('', $event->getName());
        $this->assertSame('', $event->getNimi());
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $event->getEventDate(),
        );
        $this->assertNull($event->getPublishDate());
        $this->assertNull($event->getPicture());
        $this->assertIsString($event->getCss());
        $this->assertIsString($event->getContent());
        $this->assertIsString($event->getSisallys());
        $this->assertNull($event->getUrl());
        $publicationDecider = new \App\Domain\EventTemporalStateService(
            new \App\Time\AppClock(),
        );
        $this->assertFalse($publicationDecider->isPublished($event));
        $this->assertSame('', $event->getType());
        $this->assertNull($event->getEpics());
        $this->assertFalse($event->isExternalUrl());
        $this->assertFalse($event->isSticky());
        $this->assertSame('banner', $event->getPicturePosition());
        $this->assertFalse($event->isCancelled());
        $this->assertFalse($event->isMultiday());
        $this->assertNull($event->getAttachment());
        $this->assertIsArray($event->getLinks());
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getEventArtistInfos(),
        );
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getRSVPs(),
        );
        // Nakki-related methods moved to Nakkikone entity
        $this->assertNull($event->getNakkikone()); // No nakkikone by default
        $this->assertFalse($event->getIncludeSaferSpaceGuidelines());
        $this->assertSame('light', $event->getHeaderTheme());
        $this->assertNull($event->getStreamPlayerUrl());
        $this->assertNull($event->getImgFilterColor());
        $this->assertNull($event->getImgFilterBlendMode());
        $this->assertFalse($event->isArtistSignUpEnabled());
        $this->assertNull($event->getArtistSignUpEnd());
        $this->assertNull($event->getArtistSignUpStart());
        $this->assertNull($event->getWebMeetingUrl());
        $this->assertFalse($event->isShowArtistSignUpOnlyForLoggedInMembers());
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getTickets(),
        );
        $this->assertSame(0, $event->getTicketCount());
        $this->assertFalse($event->isTicketsEnabled());
        $this->assertNull($event->getTicketPrice());
        $this->assertNull($event->getTicketInfoFi());
        $this->assertNull($event->getTicketInfoEn());
        $this->assertNull($event->getTicketPresaleStart());
        $this->assertNull($event->getTicketPresaleEnd());
        $this->assertSame(0, $event->getTicketPresaleCount());
        // Nakki-related methods moved to Nakkikone entity
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getEmails(),
        );
        $this->assertFalse($event->isRsvpOnlyToActiveMembers());
        $this->assertNull($event->getBackgroundEffect());
        $this->assertNull($event->getBackgroundEffectOpacity());
        $this->assertNull($event->getBackgroundEffectPosition());
        $this->assertNull($event->getBackgroundEffectConfig());
        $this->assertTrue($event->isArtistSignUpAskSetLength());
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getNotifications(),
        );
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getHappenings(),
        );
        // Nakki-related methods moved to Nakkikone entity
        $this->assertTrue($event->isAllowMembersToCreateHappenings());
        $this->assertNull($event->getLocation());
        $this->assertSame('event.html.twig', $event->getTemplate());
        $this->assertNull($event->getAbstractFi());
        $this->assertNull($event->getAbstractEn());
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getProducts(),
        );
        $this->assertNull($event->getArtistSignUpInfoFi());
        $this->assertNull($event->getArtistSignUpInfoEn());
        // $this->assertSame(1, $event->getVersion());
        $this->assertFalse($event->isSendRsvpEmail());
        $this->assertNull($event->getLinkToForums());
        $this->assertNull($event->getWikiPage());
    }

    public function testSettersAndGetters(): void
    {
        $event = new Event();

        $event->setName('Test Event');
        $this->assertSame('Test Event', $event->getName());

        $event->setNimi('Tapahtuma');
        $this->assertSame('Tapahtuma', $event->getNimi());

        $date = new \DateTimeImmutable('2025-10-10 12:00:00');
        $event->setEventDate($date);
        $this->assertSame($date, $event->getEventDate());

        $event->setPublishDate($date);
        $this->assertSame($date, $event->getPublishDate());

        $event->setCss('body { color: red; }');
        $this->assertSame('body { color: red; }', $event->getCss());

        $event->setContent('Content here');
        $this->assertSame('Content here', $event->getContent());

        $event->setSisallys('Sisältö tässä');
        $this->assertSame('Sisältö tässä', $event->getSisallys());

        $event->setUrl('https://example.com');
        $this->assertSame('https://example.com', $event->getUrl());

        $event->setPublished(true);
        $event->setPublishDate(new \DateTimeImmutable('now'));
        $publicationDecider = new \App\Domain\EventTemporalStateService(
            new \App\Time\AppClock(),
        );
        $this->assertTrue($publicationDecider->isPublished($event));

        $event->setType('concert');
        $this->assertSame('concert', $event->getType());

        $event->setEpics('epics123');
        $this->assertSame('epics123', $event->getEpics());

        $event->setExternalUrl(true);
        $this->assertTrue($event->isExternalUrl());

        $event->setSticky(true);
        $this->assertTrue($event->isSticky());

        $event->setPicturePosition('footer');
        $this->assertSame('footer', $event->getPicturePosition());

        $event->setCancelled(true);
        $this->assertTrue($event->isCancelled());

        $event->setMultiday(true);
        $this->assertTrue($event->isMultiday());

        $event->setLinks([['url' => 'https://a.com', 'title' => 'A']]);
        $this->assertIsArray($event->getLinks());
        $this->assertSame('https://a.com', $event->getLinks()[0]['url']);
    }

    public function testLifecycleHooks(): void
    {
        $event = new Event();

        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $event->getUpdatedAt(),
        );
        $event->setCreatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $event->getUpdatedAt(),
        );

        $oldUpdated = $event->getUpdatedAt();
        usleep(1000);
        $event->setUpdatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $event->getUpdatedAt(),
        );
        $this->assertNotEquals($oldUpdated, $event->getUpdatedAt());
    }

    public function testCollections(): void
    {
        $event = new Event();

        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getEventArtistInfos(),
        );
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getRSVPs(),
        );
        // Nakki-related methods moved to Nakkikone entity
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getTickets(),
        );
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getEmails(),
        );
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getNotifications(),
        );
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getHappenings(),
        );
        // Nakki-related methods moved to Nakkikone entity
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $event->getProducts(),
        );
    }

    public function testDomainHelpers(): void
    {
        $event = new Event();
        $event->setName('Test Event');
        $this->assertSame('Test Event', (string) $event);
    }
}
