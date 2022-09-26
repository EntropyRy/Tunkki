<?php

namespace App\PageService;

use App\Entity\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;

class EventsPage implements PageServiceInterface
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
    public function getName(): string
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = array(), Response $response = null): Response
    {
        $events = $this->em->getRepository(Event::class)->findPublicEventsByNotType('announcement');
        //$clubroom = $this->em->getRepository('App:Event')->findEventsByType('clubroom');
        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            array_merge($parameters, array('events'=>$events)), //'clubroom'=>$clubroom)),
            $response
        );
    }
}
