<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu;

use App\Entity\Menu;
use App\Menu\MenuBuilder;
use App\Repository\MenuRepository;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MenuBuilderTest extends TestCase
{
    public function testCreateMainMenuUsesLocaleLabelsAndSortsByPosition(): void
    {
        $root = $this->createMenu(
            label: 'Events',
            nimi: 'Tapahtumat',
            url: '/events',
            position: 5,
        );

        $firstChild = $this->createMenu(
            label: 'Tickets',
            nimi: 'Liput',
            url: '/tickets',
            position: 2,
        );
        $secondChild = $this->createMenu(
            label: 'About',
            nimi: 'Tietoa',
            url: '/about',
            position: 1,
        );

        $root->addChild($firstChild);
        $root->addChild($secondChild);

        /** @var MenuRepository&MockObject $repo */
        $repo = $this->createMock(MenuRepository::class);
        $repo
            ->method('getRootNodes')
            ->willReturn([$root]);

        $builder = new MenuBuilder(new MenuFactory(), $repo);

        $fiMenu = $builder->createMainMenu(['locale' => 'fi']);
        $this->assertSame(
            ['Tapahtumat', 'Tietoa', 'Liput'],
            array_keys($fiMenu->getChildren()),
            'Finnish locale should render Finnish labels and sorted children.',
        );

        $enMenu = $builder->createMainMenu(['locale' => 'en']);
        $this->assertSame(
            ['Events', 'About', 'Tickets'],
            array_keys($enMenu->getChildren()),
            'English locale should render English labels and respect ordering.',
        );

        $rootItem = $fiMenu->getChild('Tapahtumat');
        $this->assertSame('level-1', $rootItem->getLinkAttribute('class'));
    }

    public function testDropdownSkipsDisabledChildrenAndAssignsLevelClasses(): void
    {
        $root = $this->createMenu(
            label: 'Navigation',
            nimi: 'Navigaatio',
            url: '/nav',
            position: 1,
        );

        $dropdown = $this->createMenu(
            label: 'Programs',
            nimi: 'Ohjelmat',
            url: '#',
            position: 1,
        );

        $enabledChild = $this->createMenu(
            label: 'Workshops',
            nimi: 'Työpajat',
            url: '/workshops',
            position: 5,
        );
        $enabledGrandChild = $this->createMenu(
            label: 'Afterparty',
            nimi: 'Jatkot',
            url: '/afterparty',
            position: 1,
        );
        $enabledChild->addChild($enabledGrandChild);

        $disabledChild = $this->createMenu(
            label: 'Hidden',
            nimi: 'Piilotettu',
            url: '/hidden',
            position: 1,
            enabled: false,
        );

        $dropdown->addChild($disabledChild);
        $dropdown->addChild($enabledChild);
        $root->addChild($dropdown);

        /** @var MenuRepository&MockObject $repo */
        $repo = $this->createMock(MenuRepository::class);
        $repo
            ->method('getRootNodes')
            ->willReturn([$root]);

        $builder = new MenuBuilder(new MenuFactory(), $repo);
        $menu = $builder->createMainMenu(['locale' => 'en']);

        $dropdownItem = $menu->getChild('Programs');
        self::assertNotNull($dropdownItem, 'Dropdown parent should exist.');

        $dropdownChildren = $dropdownItem->getChildren();
        $this->assertArrayHasKey('Workshops', $dropdownChildren);
        $this->assertArrayNotHasKey('Hidden', $dropdownChildren, 'Disabled child must be skipped.');

        $workshopsItem = $dropdownChildren['Workshops'];
        $this->assertSame('level-2', $workshopsItem->getLinkAttribute('class'));

        $this->assertArrayHasKey('Afterparty', $dropdownChildren);
        $grandChildItem = $dropdownChildren['Afterparty'];
        $this->assertSame('level-3', $grandChildItem->getLinkAttribute('class'));
    }

    private function createMenu(
        string $label,
        string $nimi,
        string $url,
        int $position,
        bool $enabled = true,
    ): Menu {
        $menu = new Menu();
        $menu
            ->setLabel($label)
            ->setNimi($nimi)
            ->setUrl($url)
            ->setPosition($position)
            ->setEnabled($enabled)
            ->setLft(0)
            ->setRgt(0)
            ->setLvl(0);

        return $menu;
    }
}
