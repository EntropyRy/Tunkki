<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\EventArtistInfoAdmin;
use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use App\Factory\ArtistFactory;
use App\Factory\EventArtistInfoFactory;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Zenstruck\Foundry\Proxy;

#[Group('admin')]
#[Group('event-artist-info')]
final class EventArtistInfoAdminTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
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
        self::assertSame('Clone Artist', $clone->getName());
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

    public function testDatagridFiltersIncludeExpectedFields(): void
    {
        $datagrid = $this->admin()->getDatagrid();

        self::assertTrue($datagrid->hasFilter('Artist.type'));
        self::assertTrue($datagrid->hasFilter('Artist'));
        self::assertTrue($datagrid->hasFilter('SetLength'));
        self::assertTrue($datagrid->hasFilter('stage'));
        self::assertTrue($datagrid->hasFilter('StartTime'));
    }

    public function testListFieldsIncludeExpectedColumns(): void
    {
        $list = $this->admin()->getList();

        self::assertTrue($list->has('ArtistClone.linkUrls'));
        self::assertTrue($list->has('Artist.member'));
        self::assertTrue($list->has('WishForPlayTime'));
        self::assertTrue($list->has('freeWord'));
        self::assertTrue($list->has('stage'));
        self::assertTrue($list->has('StartTime'));
        self::assertTrue($list->has(ListMapper::NAME_ACTIONS));
    }

    public function testConfigureExportFieldsIncludesArtistCloneAndTiming(): void
    {
        $admin = $this->admin();
        $method = new \ReflectionMethod($admin, 'configureExportFields');
        $method->setAccessible(true);
        $fields = $method->invoke($admin);

        self::assertContains('artistClone.name', $fields);
        self::assertContains('artistClone.genre', $fields);
        self::assertContains('WishForPlayTime', $fields);
        self::assertContains('SetLength', $fields);
        self::assertContains('StartTime', $fields);
    }

    public function testPrePersistKeepsCloneNameForDuplicateArtist(): void
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
        self::assertSame('Dup Artist', $clone->getName());
    }

    private function admin(): EventArtistInfoAdmin
    {
        $admin = static::getContainer()->get('admin.event_artist_info');
        \assert($admin instanceof EventArtistInfoAdmin);

        return $admin;
    }
}
