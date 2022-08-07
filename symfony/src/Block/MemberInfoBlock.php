<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Validator\ErrorElement;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\UrlsType;

class MemberInfoBlock extends BaseBlockService
{
    protected $security;

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $member = $this->security->getUser()->getMember();
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'member'    => $member
        ], $response);
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
        $this->buildCreateForm($formMapper, $block);
    }
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block)
    {
        /*		$formMapper
                    ->add('settings', ImmutableArrayType::class, [
                        'keys' => [
                            ['title', TextType::class, [
                                'label' => 'List Title',
                            ]],
                            ['show', ChoiceType::class,[
                                'choices' => [
                                    'Everybody can see this' => false,
                                    'Show only to logged in user' => true,
                                ]
                            ]],
                            ['urls', CollectionType::class, [
                                'required' => false,
                                'allow_add' => true,
                                'allow_delete' => true,
                                'prototype' => true,
                                'by_reference' => false,
                                'allow_extra_fields' => true,
                                'entry_type' => UrlsType::class,
                            ]],
                        ]
                    ]);*/
    }

    public function __construct($twig, Security $security) //, EntityManagerInterface $em)
    {
        $this->security = $security;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'template' => 'block/member_info.html.twig',
        ]);
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
    }
    public function getName()
    {
        return 'Member Info Block';
    }
}
