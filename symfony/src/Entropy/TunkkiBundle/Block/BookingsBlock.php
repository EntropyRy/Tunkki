<?php
namespace Entropy\TunkkiBundle\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Validator\ErrorElement;
/**
 * Description of BookingBlock
 *
 * @author H
 */
class BookingsBlock extends BaseBlockService {

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block) 
    {
        $formMapper;
    }

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
        ), $response);
    }

    public function __construct($name,$templating)
    {
        parent::__construct($name,$templating);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults(array(
            'position' => '1',
            'template' => 'EntropyTunkkiBundle:Block:bookings.html.twig',
        ));
    }
}

