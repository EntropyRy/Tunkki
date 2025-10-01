<?php

namespace App\PageService;

use App\Entity\Event;
use App\Repository\EventRepository;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use App\Helper\ePics;
use App\Repository\EventArtistInfoRepository;

class FrontPage implements PageServiceInterface
{
    public function __construct(
        private $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly EventArtistInfoRepository $eventArtistR,
        private readonly EventRepository $eventR,
        // private readonly ePics $ePics,
        private readonly ?SeoPageInterface $seoPage = null
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
        $events = $announcement instanceof Event ? array_merge($future, [$announcement]) : $future;
        $this->updateSeoPage($page);

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
    private function updateSeoPage(PageInterface $page): void
    {
        if (!$this->seoPage instanceof SeoPageInterface) {
            return;
        }

        $title = $page->getTitle();
        if (null !== $title) {
            $this->seoPage->setTitle($title);
        }

        $metaDescription = $page->getMetaDescription();
        if (null !== $metaDescription) {
            $this->seoPage->addMeta('name', 'description', $metaDescription);
            $this->seoPage->addMeta('property', 'og:description', $metaDescription);
        }

        $metaKeywords = $page->getMetaKeyword();
        if (null !== $metaKeywords) {
            $this->seoPage->addMeta('name', 'keywords', $metaKeywords);
        }

        $this->seoPage->addMeta('property', 'og:type', 'article');
        $this->seoPage->addHtmlAttributes('prefix', 'og: http://ogp.me/ns#');
    }
}
