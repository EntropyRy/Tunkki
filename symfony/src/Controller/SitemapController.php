<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\MenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(private EventRepository $eventRepo, private MenuRepository $menuRepo)
    {
    }

    #[Route('/sitemap.xml', name: 'sitemap')]
    public function index()
    {
        // find published events from db
        $events = $this->eventRepo->getSitemapEvents();
        $roots = $this->menuRepo->getRootNodes();
        $urls = [];
        $defaultLangs = ['fi', 'en'];
        foreach ($events as $event) {
            foreach ($defaultLangs as $lang) {
                $alt[$lang] = $event->getUrlByLang($lang);
            }
            $urls[] = [
                'loc' => $event->getUrlByLang('fi'),
                'lastmod' => $event->getUpdatedAt()->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.5',
                'alts' => $alt
            ];
        }
        foreach ($roots as $root) {
            foreach ($defaultLangs as $lang) {
                $page = $root->getPageByLang($lang);
                $alt[$lang] = $page->getUrl();
            }
            $pageFi = $root->getPageByLang('fi');
            $urls[] = [
                'loc' => $pageFi->getUrl(),
                'lastmod' => $pageFi->getUpdatedAt()->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.5',
                'alts' => $alt
            ];
            foreach ($root->getChildren() as $item) {
                if ($item->getEnabled()) {
                    foreach ($defaultLangs as $lang) {
                        $page = $item->getPageByLang($lang);
                        $alt[$lang] = $page->getUrl();
                    }
                    $pageFi = $item->getPageByLang('fi');
                    $urls[] = [
                        'loc' => $pageFi->getUrl(),
                        'lastmod' => $pageFi->getUpdatedAt()->format('Y-m-d'),
                        'changefreq' => 'weekly',
                        'priority' => '0.5',
                        'alts' => $alt
                    ];
                    if ($item->hasChildren()) {
                        foreach ($item->getChildren() as $itemLv2) {
                            if ($itemLv2->getEnabled() && empty($itemLv2->getUrl())) {
                                foreach ($defaultLangs as $lang) {
                                    $page = $itemLv2->getPageByLang($lang);
                                    $alt[$lang] = $page->getUrl();
                                }
                                $pageFi = $itemLv2->getPageByLang('fi');
                                $urls[] = [
                                    'loc' => $pageFi->getUrl(),
                                    'lastmod' => $pageFi->getUpdatedAt()->format('Y-m-d'),
                                    'changefreq' => 'weekly',
                                    'priority' => '0.5',
                                    'alts' => $alt
                                ];
                            }
                        }
                    }
                }
            }
        }
        // 'loc' => $this->generateUrl(
        //   'post',
        //   ['slug' => $event->getSlug()],
        //   UrlGeneratorInterface::ABSOLUTE_URL
        // ),

        $response = new Response(
            $this->renderView('sitemap.html.twig', ['urls' => $urls]),
            200
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
