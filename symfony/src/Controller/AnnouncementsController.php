<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AnnouncementsController extends AbstractController
{
    #[
        Route(
            path: [
                'fi' => '/tiedotukset',
                'en' => '/announcements',
            ],
            name: 'announcements',
            priority: 300,
        ),
    ]
    public function index(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findPublicEventsByType('announcement');

        return $this->render('announcements.html.twig', [
            'events' => $events,
        ]);
    }
}
