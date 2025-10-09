<?php

declare(strict_types=1);

namespace App\PageService;

use App\Entity\Event;
use App\Repository\EventRepository;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AllEventsPage implements PageServiceInterface
{
    public function __construct(
        private $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly EventRepository $eventR,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function execute(PageInterface $page, Request $request, array $parameters = [], ?Response $response = null): Response
    {
        $events = $this->eventR->findBy(['published' => true, 'sticky' => false]);
        $sticky = $this->eventR->findOneBy(['published' => true, 'sticky' => true]);
        if ($sticky instanceof Event) {
            $events = array_merge([$sticky], $events);
        }

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            ['events' => $events],
            $response
        );
    }
}
