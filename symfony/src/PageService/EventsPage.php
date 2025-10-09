<?php

declare(strict_types=1);

namespace App\PageService;

use App\Entity\User;
use App\Repository\EventRepository;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EventsPage implements PageServiceInterface
{
    public function __construct(
        private readonly string $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly EventRepository $eventRepository,
        private readonly AssetMapperInterface $assetMapper,
        private readonly SeoPageInterface $seoPage,
        private readonly Security $security,
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
        $user = $this->security->getUser();
        if ($user instanceof User && $user->getMember()->getIsActiveMember()) {
            $events = $this->eventRepository->findAllByNotType('announcement');
        } else {
            $events = $this->eventRepository->findPublicEventsByNotType('announcement');
        }

        $host = $request->getSchemeAndHttpHost();
        $this->updateSeoPage($page, $host);

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [...$parameters, ...['events' => $events]], // 'clubroom'=>$clubroom)),
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

        $this->seoPage->addMeta('property', 'twitter:image', $host.$this->assetMapper->getPublicPath('images/header-logo.svg'));

        $this->seoPage->addMeta('property', 'og:image', $host.$this->assetMapper->getPublicPath('images/header-logo.svg'));
        $this->seoPage->addMeta('property', 'og:type', 'article');
        $this->seoPage->addHtmlAttributes('prefix', 'og: http://ogp.me/ns#');
    }
}
