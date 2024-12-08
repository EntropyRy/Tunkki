<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Sonata\BlockBundle\Meta\Metadata;
use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class BookingsBlock extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $bookings = $this->em->getRepository(Booking::class)->findBy([
            'itemsReturned' => false,
            'cancelled' => false
        ], [
            'bookingDate' => 'DESC'
        ]);

        return $this->renderResponse($blockContext->getTemplate(), ['block'     => $blockContext->getBlock(), 'bookings'  => $bookings], $response);
    }

    public function __construct(Environment $twig, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['position' => '1', 'template' => 'block/bookings.html.twig']);
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), ($code ?? $this->getName()), null, 'messages', [
            'class' => 'fa fa-book',
        ]);
    }
    public function getName(): string
    {
        return 'Bookings Block';
    }
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block): void
    {
    }
}
