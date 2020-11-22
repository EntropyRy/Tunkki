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
use Doctrine\ORM\EntityManagerInterface;

class DoorInfoBlock extends BaseBlockService {

    protected $security;
    protected $em;

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $member = $this->security->getUser()->getMember();
        $logs = $this->em->getRepository('App:DoorLog')->getLatest(3);
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'logs'    => $logs,
            'member'    => $member
        ], $response);
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block) {
        $this->buildCreateForm($formMapper, $block);
    }
	public function buildCreateForm(FormMapper $formMapper, BlockInterface $block) {
    }

    public function __construct($twig, Security $security, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->security = $security;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'template' => 'block/door_info.html.twig',
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
        return 'Door Info Block';
    }

}

