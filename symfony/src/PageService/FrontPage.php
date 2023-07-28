<?php

namespace App\PageService;

use App\Repository\EventRepository;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use App\Helper\ePics;
use App\Repository\EventArtistInfoRepository;

class FrontPage implements PageServiceInterface
{
    public function __construct(
        private $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly EventArtistInfoRepository $eventArtistR,
        private readonly EventRepository $eventR,
        private readonly ePics $ePics,
    ) {
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = [], Response $response = null): Response
    {
        $events = [];
        $future = $this->eventR->getFutureEvents();
        $unpublished = $this->eventR->getUnpublishedFutureEvents();
        $info = $this->eventArtistR->findOnePublicEventArtistInfo();
        $announcement = $this->eventR->findOneEventByType('announcement');
        //$event = $r->findOneEventByTypeWithSticky('event');
        //$clubroom = $r->findOneEventByTypeWithSticky('clubroom');
        /*if ($clubroom->getEventDate() > $event->getEventDate()){
            $events = [$clubroom, $event];
        } else {
            $events = [$event, $clubroom];
        }*/
        /*if ($announcement->getEventDate() > $events[0]->getEventDate()){
            $events = array_merge([$announcement], $events);
        } else {
            $events = array_merge($events, [$announcement]);
        }*/
        // $epic = $this->ePics->getRandomPic();
        $future = array_merge($future, $unpublished);
        $events = array_merge($future, [$announcement]);

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [
                'events' => $events,
                // 'epic' => $epic,
                'info' => $info
            ],
            $response
        );
    }
}
