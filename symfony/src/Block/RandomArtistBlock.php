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
use App\Entity\Artist;
use Doctrine\ORM\EntityManagerInterface;

class RandomArtistBlock extends BaseBlockService
{
    protected $em;
    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $artists = $this->em->getRepository(Artist::class)->findBy(['copyForArchive' => false]);
        shuffle($artists);
        $artist = array_pop($artists);
        return $this->renderResponse($blockContext->getTemplate(), array(
            'block'     => $blockContext->getBlock(),
            'artist'  => $artist,
            'settings' => $blockContext->getSettings()
        ), $response);
    }

    public function __construct($twig, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'template' => 'block/random_artist.html.twig',
            'box' => false
        ]);
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-music',
        ]);
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
    }
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block)
    {
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
    }
    public function getName()
    {
        return 'Random Artist Block';
    }
}
