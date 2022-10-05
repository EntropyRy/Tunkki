<?php

namespace App\PageService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;
use App\Entity\Event;

class FrontPage implements PageServiceInterface
{
    public function __construct(
        private $name,
        private readonly TemplateManager $templateManager,
        private readonly EntityManagerInterface $em
    )
    {
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = [], Response $response = null): Response
    {
        $r = $this->em->getRepository(Event::class);
        $events =[];
        $future = $r->getFutureEvents();
        $announcement = $r->findOneEventByTypeWithSticky('announcement');
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
        $events = array_merge($future, [$announcement]);

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [...$parameters, ...['events'=>$events]], //'clubroom'=>$clubroom)),
            $response
        );
    }
}
