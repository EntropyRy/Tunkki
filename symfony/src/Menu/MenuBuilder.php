<?php
namespace App\Menu;

use Knp\Menu\FactoryInterface;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;


class MenuBuilder
{
    private $factory;
    private $em;
    private $client;

    /**
     * @param FactoryInterface $factory
     *
     * Add any other dependency you need
     */
    public function __construct(FactoryInterface $factory, EntityManagerInterface $em, HttpClientInterface $client)
    {
        $this->factory  = $factory;
        $this->em       = $em;
        $this->client   = $client;
    }

    public function createMainMenuFi(array $options)
    {
        $locale = 'fi';
        $roots = $this->em->getRepository(Menu::class)->getRootNodes();
        $menu = $this->factory->createItem('root');
        $menu->setChildrenAttribute('class', 'navbar-nav');
        foreach ($roots as $m){
            $menu = $this->addItem($menu, $m, $locale);
            $m = $this->sortByPosition($m);
            foreach ($m as $item){
                if($item->getEnabled()){
                    if($item->getUrl() == '#'){
                        $dropdown = $menu->addChild(
                            $item->getNimi(),
                            [
                                'attributes' => [
                                    'dropdown' => true,
                                ],
                            ]
                        );
                        foreach ($item->getChildren() as $subitem){
                            $dropdown = $this->addItem($dropdown, $subitem, $locale);
                        }
                    } else {
                        $menu = $this->addItem($menu, $item, $locale);
                    }
                }
            }
        }
        // dynamically add stream
        $this->addStream($menu);
        return $menu;
    }
    public function createMainMenuEn(array $options)
    {
        $locale = 'en';
        $roots = $this->em->getRepository(Menu::class)->getRootNodes();
        $menu = $this->factory->createItem('root');
        $menu->setChildrenAttribute('class', 'navbar-nav mr-auto');
        foreach ($roots as $m){
            $menu = $this->addItem($menu, $m, $locale);
            $m = $this->sortByPosition($m);
            foreach ($m as $item){
                if($item->getEnabled()){
                if($item->getUrl() == '#'){
                    $dropdown = $menu->addChild(
                        $item->getLabel(),
                        [
                            'attributes' => [
                                'dropdown' => true,
                            ],
                        ]
                    );
                    foreach ($item->getChildren() as $subitem){
                        $dropdown = $this->addItem($dropdown, $subitem, $locale);
                    }
                } else {
                    $menu = $this->addItem($menu, $item, $locale);
                }
                }
            }
        }
        $this->addStream($menu);
        return $menu;
    }
    private function addItem($menu, $m,$l)
    {
        if($l == 'fi'){
            if($m->getPageFi()){
                $menu->addChild($m->getNimi(), ['route' => 'page_slug',
                    'routeParameters' => ['path' => '/'.$m->getPageFi()->getSlug()]]);
            } else {
                $menu->addChild($m->getNimi(), ['uri' => $m->getUrl()]);
            }
        } else {
            if($m->getPageEn()){
                if (strpos($m->getPageEn()->getSlug(), '/en') !== false){
                    $prefix = '/en';
                } else {
                    $prefix = '';
                }
                $url = $prefix.'/'.$m->getPageEn()->getSlug();
                $menu->addChild($m->getLabel(), [
                    'route' => 'page_slug',
                    'routeParameters' => ['path' => $url ]
                ]);
            } else {
                if (strpos($m->getUrl(), 'http') !== false){
                    $menu->addChild($m->getLabel(), ['uri' => $m->getUrl()]);
                } else {
                    $menu->addChild($m->getLabel(), ['uri' => '/en'.$m->getUrl()]);
                }
            }
        }
        return $menu;
    }
    private function addStream($menu)
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://stream.entropy.fi/kerde.opus',
                ['max_duration' => 4]
            );
            if ($response->getStatusCode() == 200) {
                $menu->addChild('Stream', ['uri' => 'https://stream.entropy.fi/'])->setLinkAttribute('class', 'hilight');
            }
        } catch (TransportExceptionInterface $e) {
            return;
        }
    }
    private function sortByPosition($m)
    {
        $array = $m->getChildren()->toArray();
        usort($array, 
            function ($a, $b){ 
                return $a->getPosition() <=> $b->getPosition();
            }
        );
        return $array;
    }
}
