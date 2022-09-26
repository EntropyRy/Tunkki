<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Doctrine\ORM\EntityManagerInterface;

class BrokenItemsBlock extends BaseBlockService
{
    protected $em;

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

    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $broken = $this->em->getRepository('App:Item')->findBy(['needsFixing' => true, 'toSpareParts' => false]);
        $settings = $blockContext->getSettings();
        if ($settings['random']) {
            shuffle($broken);
            if (count($broken)>5) {
                $l = 3;
            } else {
                $l = count($broken);
            }
            $broken = array_splice($broken, 0, $l);
        }
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'broken'  => $broken,
            'settings' => $settings
        ), $response);
    }

    public function __construct($twig, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'position' => '1',
            'random' => false,
            'bs3' => true,
            'template' => 'block/brokenitems.html.twig',
        ));
    }
}
