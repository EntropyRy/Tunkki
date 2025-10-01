<?php

namespace App\Menu;

use App\Entity\Menu;
use App\Entity\Sonata\SonataPagePage;
use App\Repository\MenuRepository;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;

class MenuBuilder
{
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly MenuRepository $mRepo,
    ) {
    }

    public function createMainMenu(array $options): ItemInterface
    {
        $locale = $options['locale'];
        $nameFunc = 'fi' == $locale ? 'getNimi' : 'getLabel';
        $roots = $this->mRepo->getRootNodes();
        $menu = $this->factory->createItem('root');
        $menu->setChildrenAttribute('class', 'navbar-nav navbar-nav-scroll');
        foreach ($roots as $m) {
            $menu = $this->addItem($menu, $m, $locale, 1);
            $m = $this->sortByPosition($m);
            foreach ($m as $item) {
                if ($item->getEnabled()) {
                    if ('#' == $item->getUrl()) {
                        $dropdown = $menu->addChild($item->{$nameFunc}(), [
                            'attributes' => [
                                'dropdown' => true,
                            ],
                        ]);
                        $items = $this->sortByPosition($item);
                        foreach ($items as $subitem) {
                            if ($subitem->getEnabled()) {
                                $dropdown = $this->addItem(
                                    $dropdown,
                                    $subitem,
                                    $locale,
                                    2
                                );
                            }
                            $subsubitems = $this->sortByPosition($subitem);
                            foreach ($subsubitems as $subsubitem) {
                                if ($subsubitem->getEnabled()) {
                                    $subdropdown = $this->addItem(
                                        $dropdown,
                                        $subsubitem,
                                        $locale,
                                        3
                                    );
                                }
                            }
                        }
                    } else {
                        $menu = $this->addItem($menu, $item, $locale);
                    }
                }
            }
        }

        return $menu;
    }

    private function addItem(
        ItemInterface $menu,
        Menu $item,
        string $locale,
        ?int $lvl = null,
    ): ItemInterface {
        $level = 'level-'.$lvl;
        $nameFunc = 'fi' === $locale ? 'getNimi' : 'getLabel';
        $page = $item->getPageByLang($locale);
        if ($page instanceof SonataPagePage) {
            // if page is set
            $url = $page->getUrl();
        } else {
            $url = $item->getUrl();
        }
        $menu->addChild($item->{$nameFunc}(), [
            'route' => 'page_slug',
            'routeParameters' => ['path' => ''.$url],
            'linkAttributes' => [
                'class' => $level,
            ],
        ]);

        return $menu;
    }

    private function sortByPosition($m): array
    {
        $array = $m->getChildren()->toArray();
        usort(
            $array,
            fn ($a, $b): int => $a->getPosition() <=> $b->getPosition()
        );

        return $array;
    }
}
