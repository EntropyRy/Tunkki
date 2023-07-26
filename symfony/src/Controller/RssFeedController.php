<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RssFeedController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/feed.rss',
            'en' => '/feed.rss',
        ],
        name: 'rss_feed',
    )]
    public function index(Request $request, EventRepository $eRepo): Response
    {
        $events = $eRepo->getRSSEvents();
        $locale = $request->getLocale();
        $response = new Response($this->renderView('rss_feed/index.xml.twig', [
            'events' => $events,
            'locale' => $locale
        ]));
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');
        return $response;
    }
}
