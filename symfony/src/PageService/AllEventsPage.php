<?php

namespace App\PageService;

use Sonata\PageBundle\Page\TemplateManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use App\Repository\EventRepository;

class AllEventsPage implements PageServiceInterface
{
    public function __construct(
        private $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly EventRepository $eventR
    ) {
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = [], Response $response = null): Response
    {
        $events = $this->eventR->findBy(['published' => true, 'sticky' => false]);
        $sticky = $this->eventR->findOneBy(['published' => true, 'sticky' => true]);
        if ($sticky) {
            $events = array_merge([$sticky], $events);
        }
        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            ['events' => $events],
            $response
        );
    }
}
