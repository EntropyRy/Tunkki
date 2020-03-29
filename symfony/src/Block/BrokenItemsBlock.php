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

class BrokenItemsBlock extends BaseBlockService {

    protected $em;

    public function buildiCreateForm(FormMapper $formMapper, BlockInterface $block) 
    {
    }
    public function buildiEditForm(FormMapper $formMapper, BlockInterface $block) 
    {
    }
    public function getName() 
    {
        return 'Broken Items Block';
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $broken = $this->em->getRepository('App:Item')->findBy(['needsFixing' => true, 'toSpareParts' => false]);
        //$broken = null; 
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'broken'  => $broken
        ), $response);
    }

    public function __construct($twig, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'block/brokenitems.html.twig',
        ));
    }
}

