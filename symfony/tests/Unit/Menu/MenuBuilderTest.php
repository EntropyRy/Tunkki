<?php

declare(strict_types=1);

namespace App\Tests\Unit\Menu;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use App\Menu\MenuBuilder;
use App\Repository\MenuRepository;
use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MenuBuilderTest extends TestCase
{
    public function testCreateMainMenuUsesLocaleLabelsAndSortsByPosition(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static fn (string $route, array $params = []): string => $route.'?'.http_build_query($params));

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator));

        $root = $this->createMenu(
            label: 'Events',
            nimi: 'Tapahtumat',
            url: '/wrong-root-url',
            position: 5,
        );
        $root
            ->setPageFi($this->createPage('/tapahtumat'))
            ->setPageEn($this->createPage('/events'));

        $firstChild = $this->createMenu(
            label: 'Tickets',
            nimi: 'Liput',
            url: '/tickets',
            position: 2,
        );
        // FI uses the configured page URL; EN falls back to Menu::$url.
        $firstChild->setPageFi($this->createPage('/liput'));
        $secondChild = $this->createMenu(
            label: 'About',
            nimi: 'Tietoa',
            url: '/about',
            position: 1,
        );

        $root->addChild($firstChild);
        $root->addChild($secondChild);

        /** @var MenuRepository $menuRepository */
        $menuRepository = $this->createStub(MenuRepository::class);
        $menuRepository
            ->method('getRootNodes')
            ->willReturn([$root]);

        $builder = new MenuBuilder($factory, $menuRepository);

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
        $this->assertSame(
            [['route' => 'page_slug', 'parameters' => ['path' => '/tapahtumat']]],
            $rootItem->getExtra('routes'),
            'Finnish root item should use the configured page URL.',
        );

        // Note: MenuBuilder adds top-level items as siblings (not nested under the root item).
        $ticketsFi = $fiMenu->getChild('Liput');
        $this->assertSame(
            [['route' => 'page_slug', 'parameters' => ['path' => '/liput']]],
            $ticketsFi->getExtra('routes'),
            'Finnish ticket item should use the configured page URL.',
        );

        $rootItemEn = $enMenu->getChild('Events');
        $ticketsEn = $enMenu->getChild('Tickets');
        $this->assertSame(
            [['route' => 'page_slug', 'parameters' => ['path' => '/tickets']]],
            $ticketsEn->getExtra('routes'),
            'English ticket item should fall back to Menu::$url when no EN page is set.',
        );
    }

    public function testDropdownSkipsDisabledChildrenAndAssignsLevelClasses(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static fn (string $route, array $params = []): string => $route.'?'.http_build_query($params));

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator));

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
        $enabledChild->setPageEn($this->createPage('/program/workshops'));
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

        /** @var MenuRepository $repo */
        /** @var MenuRepository $repo */
        $repo = $this->createStub(MenuRepository::class);
        $repo
            ->method('getRootNodes')
            ->willReturn([$root]);

        $builder = new MenuBuilder($factory, $repo);
        $menu = $builder->createMainMenu(['locale' => 'en']);

        $dropdownItem = $menu->getChild('Programs');
        self::assertNotNull($dropdownItem, 'Dropdown parent should exist.');

        $dropdownChildren = $dropdownItem->getChildren();
        $this->assertArrayHasKey('Workshops', $dropdownChildren);
        $this->assertArrayNotHasKey('Hidden', $dropdownChildren, 'Disabled child must be skipped.');

        $workshopsItem = $dropdownChildren['Workshops'];
        $this->assertSame('level-2', $workshopsItem->getLinkAttribute('class'));
        $this->assertSame(
            [['route' => 'page_slug', 'parameters' => ['path' => '/program/workshops']]],
            $workshopsItem->getExtra('routes'),
            'If a page is configured, the item should use the page URL.',
        );

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

    private function createPage(string $url): SonataPagePage
    {
        $page = new SonataPagePage();
        $page->setUrl($url);

        return $page;
    }
}
