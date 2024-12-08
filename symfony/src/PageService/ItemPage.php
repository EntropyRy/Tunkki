<?php

namespace App\PageService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;
use App\Entity\Item;

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
        $needsfix = $this->em->getRepository(Item::class)->findBy(['needsFixing' => true, 'toSpareParts' => false]);
        return $this->templateManager->renderResponse($page->getTemplateCode(), [...$parameters, ...['fix'=>$needsfix]], $response);
    }
}
