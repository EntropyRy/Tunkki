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

        // Flatten active stream artist entities into a simple array of artist names for the template.
        $artistNames = [];
        if ($stream) {
            foreach ($stream->getArtistsOnline() as $streamArtist) {
                $name = $streamArtist->getArtist()?->getName();
                if ($name) {
                    $artistNames[] = $name;
                }
            }
        }

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [...$parameters, ...['artists' => $artistNames]],
            $response
        );
    }
}
