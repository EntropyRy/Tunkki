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

class BookingsBlock extends BaseBlockService
{
    protected $em;

    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $bookings = $this->em->getRepository(Booking::class)->findBy([
            'itemsReturned' => false,
            'cancelled' => false
        ], [
            'bookingDate' => 'DESC'
        ]);

        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'bookings'  => $bookings
        ), $response);
    }

    public function __construct($name, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($name);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'block/bookings.html.twig',
        ));
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
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
