<?php

namespace App\PageService;

use App\Entity\Stream;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StreamPage implements PageServiceInterface
{
    public function __construct(private $name, private readonly TemplateManager $templateManager, private $em)
    {
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function execute(PageInterface $page, Request $request, array $parameters = [], ?Response $response = null): Response
    {
        $stream = $this->em->getRepository(Stream::class)->findOneBy(['online' => true]);

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [...$parameters, ...['artists' => ($stream ? $stream->getArtistsOnline() : null)]], // 'clubroom'=>$clubroom)),
            $response
        );
    }
}
