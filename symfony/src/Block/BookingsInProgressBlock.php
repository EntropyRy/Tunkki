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

class BookingsInProgressBlock extends BaseBlockService
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
            'bookings'  => $bookings,
            'settings' => $blockContext->getSettings()
        ), $response);
    }

    public function __construct($twig, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver):void
    {
        $resolver->setDefaults([
            'template' => 'block/bookings_in_progress.html.twig',
            'box' => false
        ]);
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
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
