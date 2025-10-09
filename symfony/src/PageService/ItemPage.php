<?php

declare(strict_types=1);

namespace App\PageService;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemPage implements PageServiceInterface
{
    public function __construct(private $name, private readonly TemplateManager $templateManager, private readonly EntityManagerInterface $em)
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
        $needsfix = $this->em->getRepository(Item::class)->findBy(['needsFixing' => true]);

        return $this->templateManager->renderResponse($page->getTemplateCode(), [...$parameters, ...['fix' => $needsfix]], $response);
    }
}
