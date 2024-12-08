<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\Service\EditableBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Validator\ErrorElement;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\UrlsType;

class LinkListBlock extends BaseBlockService implements EditableBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings()
        ], $response);
    }
    #[\Override]
    public function configureEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
        $this->configureCreateForm($formMapper, $block);
    }
    #[\Override]
    public function configureCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
        $formMapper
            ->add('settings', ImmutableArrayType::class, [
                'keys' => [
                    ['title', TextType::class, [
                        'label' => 'List Title',
                    ]],
                    ['show', ChoiceType::class, [
                        'choices' => [
                            'Everybody can see this' => 'everybody',
                            'Show only to logged in user' => 'in',
                            'Show only to logged out user' => 'out',
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
            ]);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'title' => null,
            'show' => false,
            'urls' => null,
            'template' => 'block/links.html.twig',
        ]);
    }
    #[\Override]
    public function getMetadata(): Metadata
    {
        return new Metadata($this->getName(), null, null, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }
    #[\Override]
    public function validate(ErrorElement $errorElement, BlockInterface $block): void
    {
    }
    public function getName(): string
    {
        return 'Link List Block';
    }
}
