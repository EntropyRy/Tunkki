<?php

declare(strict_types=1);

namespace App\Block\Rental;

use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\Service\EditableBlockService;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RentalFormBlock extends BaseBlockService implements EditableBlockService
{
    #[\Override]
    public function configureCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    #[\Override]
    public function configureEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function getName(): string
    {
        return 'Rental Form Block';
    }

    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        return $this->renderResponse($blockContext->getTemplate(), ['block' => $blockContext->getBlock()], $response);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['position' => '1', 'template' => 'block/rental_form.html.twig']);
    }

    #[\Override]
    public function getMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), null, null, 'messages', [
            'class' => 'fa fa-file-text',
        ]);
    }

    #[\Override]
    public function validate(ErrorElement $errorElement, BlockInterface $block): void
    {
    }
}
