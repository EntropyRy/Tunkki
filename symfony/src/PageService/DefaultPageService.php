<?php

declare(strict_types=1);

namespace App\PageService;

use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\TemplateManagerInterface;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Sonata\PageBundle\Page\Service\BasePageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DefaultPageService extends BasePageService
{
    public function __construct(
        string $name,
        private readonly TemplateManagerInterface $templateManager,
        private readonly ?SeoPageInterface $seoPage = null
    ) {
        parent::__construct($name);
    }

    #[\Override]
    public function execute(
        PageInterface $page,
        Request $request,
        array $parameters = [],
        ?Response $response = null
    ): Response {
        $this->updateSeoPage($page);
        $templateCode = $page->getTemplateCode();
        if (null === $templateCode) {
            throw new \RuntimeException('The page template is not defined');
        }

        return $this->templateManager->renderResponse($templateCode, $parameters, $response);
    }

    private function updateSeoPage(PageInterface $page): void
    {
        if (null === $this->seoPage) {
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
