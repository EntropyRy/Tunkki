<?php

namespace App\PageService;

use App\Repository\EventRepository;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;

class EventsPage implements PageServiceInterface
{
    public function __construct(
        private readonly string $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly EventRepository $eventRepository,
        private readonly AssetMapperInterface $assetMapper,
        private readonly SeoPageInterface $seoPage
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
        $events = $this->eventRepository->findPublicEventsByNotType('announcement');
        //$clubroom = $this->em->getRepository('App:Event')->findEventsByType('clubroom');
        $host = $request->getSchemeAndHttpHost();
        $this->updateSeoPage($page, $host);
        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [...$parameters, ...['events' => $events]], //'clubroom'=>$clubroom)),
            $response
        );
    }
    private function updateSeoPage(PageInterface $page, string $host): void
    {
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

        $this->seoPage->addMeta('property', 'twitter:image', $host . $this->assetMapper->getPublicPath('images/header-logo.svg'));

        $this->seoPage->addMeta('property', 'og:image', $host . $this->assetMapper->getPublicPath('images/header-logo.svg'));
        $this->seoPage->addMeta('property', 'og:type', 'article');
        $this->seoPage->addHtmlAttributes('prefix', 'og: http://ogp.me/ns#');
    }
}
