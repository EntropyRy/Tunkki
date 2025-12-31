<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Admin\EventArtistInfoAdmin;
use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use Zenstruck\Foundry\Proxy;

#[Group('admin')]
#[Group('event-artist-info')]
final class EventArtistInfoAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testUpdateActionCopiesArtistDataToCloneAndRedirects(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-artist-info-'.uniqid('', true),
        ]);
        $artist = ArtistFactory::new()->create([
            'name' => 'Original Artist',
            'genre' => 'Ambient',
            'type' => 'dj',
            'hardware' => 'Modular',
            'bio' => 'Original bio',
            'bioEn' => 'Original bio en',
        ]);
        $info = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        $artist->setName('Updated Artist');
        $artist->setGenre('Techno');
        $artist->setType('band');
        $artist->setHardware('Laptop');
        $artist->setBio('Updated bio');
        $artist->setBioEn('Updated bio en');
        $this->em()->flush();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $admin = static::getContainer()->get('admin.event_artist_info');
        $url = $admin->generateUrl('update', ['id' => $info->getId()]);

        $this->client->request('GET', $url);
        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-success');
        $this->client->assertSelectorTextContains('.alert.alert-success', 'info updated');

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(EventArtistInfo::class)->find($info->getId());
        self::assertNotNull($reloaded);
        $clone = $reloaded->getArtistClone();
        self::assertNotNull($clone);
        self::assertSame('Updated Artist', $clone->getName());
        self::assertSame('Techno', $clone->getGenre());
        self::assertSame('band', $clone->getType());
        self::assertSame('Laptop', $clone->getHardware());
        self::assertSame('Updated bio', $clone->getBio());
        self::assertSame('Updated bio en', $clone->getBioEn());
    }

    public function testUpdateActionWarnsWhenNoArtistOrClone(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-artist-info-warning-'.uniqid('', true),
        ]);
        $info = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist(ArtistFactory::new()->create())
            ->create();
        $info->removeArtist();
        $info->setArtistClone(null);
        $this->em()->flush();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $admin = static::getContainer()->get('admin.event_artist_info');
        $url = $admin->generateUrl('update', ['id' => $info->getId()]);

        $this->client->request('GET', $url);
        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-warning');
        $this->client->assertSelectorTextContains('.alert.alert-warning', 'Nothing to do!');
    }

    public function testPrePersistCreatesArtistCloneForEvent(): void
    {
        $event = EventFactory::new()->published()->create([
            'name' => 'Clone Event',
        ]);
        $member = MemberFactory::new()->create();
        $artist = ArtistFactory::new()->withMember($member)->create([
            'name' => 'Clone Artist',
        ]);

        $info = new EventArtistInfo();
        $info->setEvent($event);
        $info->setArtist($artist);

        $admin = static::getContainer()->get('admin.event_artist_info');
        self::assertInstanceOf(EventArtistInfoAdmin::class, $admin);
        $admin->prePersist($info);

        $clone = $info->getArtistClone();
        self::assertInstanceOf(Artist::class, $clone);
        self::assertTrue((bool) $clone->getCopyForArchive());
        self::assertNull($clone->getMember());
        self::assertStringContainsString('Clone Artist for Clone Event', $clone->getName());
    }

    public function testFormFieldsAreDisabledWhenArtistExists(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'event-artist-info-form-'.uniqid('', true),
        ]);
        $infoProxy = EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist(ArtistFactory::new()->create())
            ->create();
        $info = $infoProxy instanceof Proxy ? $infoProxy->object() : $infoProxy;

        $admin = static::getContainer()->get('admin.event_artist_info');
        $admin->setSubject($info);

        $form = $admin->getFormBuilder()->getForm();

        self::assertTrue($form->get('Artist')->getConfig()->getOption('disabled'));
        self::assertTrue($form->get('ArtistClone')->getConfig()->getOption('disabled'));
    }

    public function testFormFieldsAllowArtistSelectionWhenMissing(): void
    {
        $info = new EventArtistInfo();

        $admin = static::getContainer()->get('admin.event_artist_info');
        $admin->setSubject($info);

        $form = $admin->getFormBuilder()->getForm();

        self::assertFalse($form->get('Artist')->getConfig()->getOption('disabled'));
    }

    public function testShowFieldsIncludeSetLengthAndStartTime(): void
    {
        $admin = static::getContainer()->get('admin.event_artist_info');
        $show = $admin->getShow();

        self::assertTrue($show->has('SetLength'));
        self::assertTrue($show->has('StartTime'));
    }

    public function testConfigureExportFieldsIncludesArtistCloneAndTiming(): void
    {
        $admin = static::getContainer()->get('admin.event_artist_info');
        $method = new \ReflectionMethod($admin, 'configureExportFields');
        $method->setAccessible(true);
        $fields = $method->invoke($admin);

        self::assertContains('artistClone.name', $fields);
        self::assertContains('artistClone.genre', $fields);
        self::assertContains('WishForPlayTime', $fields);
        self::assertContains('SetLength', $fields);
        self::assertContains('StartTime', $fields);
    }

    public function testPrePersistIncrementsCloneSuffixForDuplicateArtist(): void
    {
        $event = EventFactory::new()->published()->create([
            'name' => 'Dup Artist Event',
        ]);
        $artist = ArtistFactory::new()->create([
            'name' => 'Dup Artist',
        ]);

        EventArtistInfoFactory::new()
            ->forEvent($event)
            ->forArtist($artist)
            ->create();

        $info = new EventArtistInfo();
        $info->setEvent($event);
        $info->setArtist($artist);

        $admin = static::getContainer()->get('admin.event_artist_info');
        $admin->prePersist($info);

        $clone = $info->getArtistClone();
        self::assertInstanceOf(Artist::class, $clone);
        self::assertStringContainsString('Dup Artist for Dup Artist Event #2', $clone->getName());
    }
}
