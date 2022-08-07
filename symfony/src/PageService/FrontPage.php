<?php

namespace App\PageService;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;

class FrontPage implements PageServiceInterface
{
    private $templateManager;
    private $em;
    private $name;

    public function __construct($name, TemplateManager $templateManager, $em)
    {
        $this->name             = $name;
        $this->templateManager  = $templateManager;
        $this->em               = $em;
    }
    public function getName()
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = array(), Response $response = null)
    {
        $r = $this->em->getRepository('App:Event');
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
            array_merge($parameters, array('events'=>$events)), //'clubroom'=>$clubroom)),
            $response
        );
    }
}
