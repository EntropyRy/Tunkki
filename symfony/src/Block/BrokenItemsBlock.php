<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Item;
use Twig\Environment;

class BrokenItemsBlock extends BaseBlockService
{
    public function buildiCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }
    public function buildiEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }
    public function getName(): string
    {
        return 'Broken Items Block';
    }

    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $broken = $this->em->getRepository(Item::class)->findBy(['needsFixing' => true, 'toSpareParts' => false]);
        $settings = $blockContext->getSettings();
        if ($settings['random']) {
            shuffle($broken);
            if (count($broken) > 5) {
                $l = 3;
            } else {
                $l = count($broken) ?: 0;
            }
            $broken = array_splice($broken, 0, $l);
        }
        return $this->renderResponse($blockContext->getTemplate(), ['block'     => $blockContext->getBlock(), 'broken'  => $broken, 'settings' => $settings], $response);
    }

    public function __construct(Environment $twig, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['position' => '1', 'random' => false, 'bs3' => true, 'template' => 'block/brokenitems.html.twig']);
    }
}
