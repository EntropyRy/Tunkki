<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(private EventRepository $eventRepo)
    {
    }

    #[Route('/sitemap.xml', name: 'sitemap')]
    public function index()
    {
        // find published events from db
        $events = $this->eventRepo->getSitemapEvents();
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
