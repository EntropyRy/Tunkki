<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Artist;
use PHPUnit\Framework\TestCase;

class ArtistEntityTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $artist = new Artist();

        // ID should be null before persistence
        $this->assertNull($artist->getId());

        // Default values
        $this->assertSame('', $artist->getName());
        $this->assertNull($artist->getGenre());
        $this->assertNull($artist->getType());
        $this->assertNull($artist->getBio());
        $this->assertNull($artist->getHardware());
        $this->assertInstanceOf(
            \Doctrine\Common\Collections\Collection::class,
            $artist->getEventArtistInfos(),
        );
        $this->assertCount(0, $artist->getEventArtistInfos());
        $this->assertNull($artist->getMember());
        $this->assertNull($artist->getBioEn());
        $this->assertIsArray($artist->getLinks());
        $this->assertSame([], $artist->getLinks());
        $this->assertNull($artist->getPicture());
        $this->assertFalse($artist->getCopyForArchive());
    }

    public function testSettersAndGetters(): void
    {
        $artist = new Artist();

        $artist->setName('Test Artist');
        $this->assertSame('Test Artist', $artist->getName());
        $this->assertSame('Test Artist', (string) $artist);

        $artist->setGenre('Electronic');
        $this->assertSame('Electronic', $artist->getGenre());

        $artist->setType('DJ');
        $this->assertSame('DJ', $artist->getType());

        $artist->setBio('Finnish bio');
        $this->assertSame('Finnish bio', $artist->getBio());

        $artist->setHardware('CDJs');
        $this->assertSame('CDJs', $artist->getHardware());

        $artist->setBioEn('English bio');
        $this->assertSame('English bio', $artist->getBioEn());

        $artist->setLinks([
            ['url' => 'https://example.com', 'title' => 'Homepage'],
        ]);
        $this->assertIsArray($artist->getLinks());
        $this->assertCount(1, $artist->getLinks());
        $this->assertSame('https://example.com', $artist->getLinks()[0]['url']);

        $artist->setCopyForArchive(true);
        $this->assertTrue($artist->getCopyForArchive());

        $artist->setCopyForArchive(false);
        $this->assertFalse($artist->getCopyForArchive());
    }

    public function testLifecycleHooks(): void
    {
        $artist = new Artist();
        $artist->setCreatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $artist->getCreatedAt(),
        );
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $artist->getUpdatedAt(),
        );

        $oldUpdated = $artist->getUpdatedAt();
        usleep(1000);
        $artist->setUpdatedAtValue();
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $artist->getUpdatedAt(),
        );
        $this->assertNotEquals($oldUpdated, $artist->getUpdatedAt());
    }

    public function testEventArtistInfosCollection(): void
    {
        $artist = new Artist();
        $this->assertCount(0, $artist->getEventArtistInfos());

        $mockEventArtistInfo = $this->createMock(
            \App\Entity\EventArtistInfo::class,
        );
        $mockEventArtistInfo
            ->expects($this->any())
            ->method('setArtist')
            ->with($artist);

        $artist->addEventArtistInfo($mockEventArtistInfo);
        $this->assertCount(1, $artist->getEventArtistInfos());

        $artist->removeEventArtistInfo($mockEventArtistInfo);
        $this->assertCount(0, $artist->getEventArtistInfos());

        $artist->addEventArtistInfo($mockEventArtistInfo);
        $artist->clearEventArtistInfos();
        $this->assertCount(0, $artist->getEventArtistInfos());
    }

    public function testMemberRelation(): void
    {
        $artist = new Artist();
        $mockMember = $this->createMock(\App\Entity\Member::class);
        $mockMember->expects($this->once())->method('addArtist')->with($artist);

        $artist->setMember($mockMember);
        $this->assertSame($mockMember, $artist->getMember());
    }

    public function testBioByLocale(): void
    {
        $artist = new Artist();
        $artist->setBio('Finnish bio');
        $artist->setBioEn('English bio');

        $this->assertSame('Finnish bio', $artist->getBioByLocale('fi'));
        $this->assertSame('English bio', $artist->getBioByLocale('en'));
        $this->assertSame('English bio', $artist->getBioByLocale('sv'));
    }

    public function testPictureSetterGetter(): void
    {
        $artist = new Artist();
        $mockPicture = $this->createMock(
            \App\Entity\Sonata\SonataMediaMedia::class,
        );
        $artist->setPicture($mockPicture);
        $this->assertSame($mockPicture, $artist->getPicture());
    }

    public function testGetLinkUrls(): void
    {
        $artist = new Artist();
        $artist->setLinks([
            ['url' => 'https://a.com', 'title' => 'A'],
            ['url' => 'https://b.com', 'title' => 'B'],
        ]);
        $result = $artist->getLinkUrls();
        $this->assertStringContainsString(
            '<a href="https://a.com">A</a>',
            $result,
        );
        $this->assertStringContainsString(
            '<a href="https://b.com">B</a>',
            $result,
        );
        $this->assertStringContainsString(' | ', $result);
    }
}
