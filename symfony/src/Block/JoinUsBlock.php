<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\Templating\EngineInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Sonata\BlockBundle\Meta\Metadata;
use Doctrine\ORM\EntityManagerInterface;

class JoinUsBlock extends BaseBlockService
{
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block)
    {
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
    }
    public function getName()
    {
        return 'Join Us Block';
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
        ), $response);
    }
    public function configureSettings(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'member/joinus_block.html.twig',
        ));
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-user',
        ]);
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
    }
}
