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
use Twig\Environment;

class RandomArtistBlock extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $artists = $this->em->getRepository(Artist::class)->findBy(['copyForArchive' => false]);
        shuffle($artists);
        $artist = array_pop($artists);
        return $this->renderResponse($blockContext->getTemplate(), ['block'     => $blockContext->getBlock(), 'artist'  => $artist, 'settings' => $blockContext->getSettings()], $response);
    }

    public function __construct(Environment $twig, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/random_artist.html.twig',
            'box' => false
        ]);
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), ($code ?? $this->getName()), null, 'messages', [
            'class' => 'fa fa-music',
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
        return 'Random Artist Block';
    }
}
