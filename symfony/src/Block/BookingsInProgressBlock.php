<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class BookingsInProgressBlock extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $bookings = $this->em->getRepository(Booking::class)->findBy([
            'itemsReturned' => false,
            'cancelled' => false,
        ], [
            'bookingDate' => 'DESC',
        ]);

        return $this->renderResponse($blockContext->getTemplate(), ['block' => $blockContext->getBlock(), 'bookings' => $bookings, 'settings' => $blockContext->getSettings()], $response);
    }

    public function __construct(Environment $twig, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/bookings_in_progress.html.twig',
            'box' => false,
        ]);
    }

    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), $code ?? $this->getName(), null, 'messages', [
            'class' => 'fa fa-bullhorn',
        ]);
    }

    public function validateBlock(ErrorElement $errorElement, BlockInterface $block): void
    {
    }

    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function getName(): string
    {
        return 'Future Bookings Block';
    }
}
