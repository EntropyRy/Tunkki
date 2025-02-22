<?php

namespace App\Menu;

use App\Repository\MenuRepository;
use Knp\Menu\FactoryInterface;

class MenuBuilder
{
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly MenuRepository $mRepo
    ) {
    }

    public function createMainMenuFi(array $options)
    {
        $locale = 'fi';
        $roots = $this->mRepo->getRootNodes();
        $menu = $this->factory->createItem('root');
        $menu->setChildrenAttribute('class', 'navbar-nav');
        foreach ($roots as $m) {
            $menu = $this->addItem($menu, $m, $locale);
            $m = $this->sortByPosition($m);
            foreach ($m as $item) {
                if ($item->getEnabled()) {
                    if ($item->getUrl() == '#') {
                        $dropdown = $menu->addChild(
                            $item->getNimi(),
                            [
                                'attributes' => [
                                    'dropdown' => true,
                                ],
                            ]
                        );
                        foreach ($item->getChildren() as $subitem) {
                            $dropdown = $this->addItem($dropdown, $subitem, $locale);
                        }
                    } else {
                        $menu = $this->addItem($menu, $item, $locale);
                    }
                }
            }
        }
        // dynamically add stream
        //$this->addStream($menu);
        return $menu;
    }
    public function createMainMenuEn(array $options)
    {
        $locale = 'en';
        $roots = $this->mRepo->getRootNodes();
        $menu = $this->factory->createItem('root');
        $menu->setChildrenAttribute('class', 'navbar-nav mr-auto');
        foreach ($roots as $m) {
            $menu = $this->addItem($menu, $m, $locale);
            $m = $this->sortByPosition($m);
            foreach ($m as $item) {
                if ($item->getEnabled()) {
                    if ($item->getUrl() == '#') {
                        $dropdown = $menu->addChild(
                            $item->getLabel(),
                            [
                                'attributes' => [
                                    'dropdown' => true,
                                ],
                            ]
                        );
                        foreach ($item->getChildren() as $subitem) {
                            $dropdown = $this->addItem($dropdown, $subitem, $locale);
                        }
                    } else {
                        $menu = $this->addItem($menu, $item, $locale);
                    }
                }
            }
        }
        return $menu;
    }
    private function addItem($menu, $m, string $l)
    {
        if ($l === 'fi') {
            if ($m->getPageFi()) {
                $menu->addChild($m->getNimi(), [
                    'route' => 'page_slug',
                    'routeParameters' => ['path' => '/' . $m->getPageFi()->getSlug()]
                ]);
            } else {
                $menu->addChild($m->getNimi(), ['uri' => $m->getUrl()]);
            }
        } elseif ($m->getPageEn()) {
            $prefix = str_contains((string) $m->getPageEn()->getSlug(), '/en') ? '/en' : '';
            $url = $prefix . '/' . $m->getPageEn()->getSlug();
            $menu->addChild($m->getLabel(), [
                'route' => 'page_slug',
                'routeParameters' => ['path' => $url]
            ]);
        } elseif (str_contains((string) $m->getUrl(), 'http')) {
            $menu->addChild($m->getLabel(), ['uri' => $m->getUrl()]);
        } else {
            $menu->addChild($m->getLabel(), ['uri' => '/en' . $m->getUrl()]);
        }
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
