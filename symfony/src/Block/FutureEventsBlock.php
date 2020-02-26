<?php
namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractAdminBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\Templating\EngineInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Validator\ErrorElement;
use Sonata\BlockBundle\Meta\Metadata;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
/**
 * @author H
 */
class FutureEventsBlock extends BaseBlockService {

    protected $em;
    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $events = $this->em->getRepository(Event::class)->getFutureEvents();
         
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'events'  => $events
        ), $response);
    }

    public function __construct($name, EngineInterface $templating, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($name, $templating);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'template' => 'block/future_events.html.twig',
        ]);
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-picture-o',
        ]);
    }
}

