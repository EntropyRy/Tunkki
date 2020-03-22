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
use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;

class BookingsBlock extends BaseBlockService {

    protected $em;

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $bookings = $this->em->getRepository(Booking::class)->findBy([
            'itemsReturned' => false, 
            'cancelled' => false
        ],[
            'bookingDate' => 'DESC'
        ]);
         
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'bookings'  => $bookings
        ), $response);
    }

    public function __construct($name,EngineInterface $templating, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($name,$templating);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'block/bookings.html.twig',
        ));
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-book',
        ]);
    }
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block)
    {
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
    }

}

