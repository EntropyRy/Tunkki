<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\Artist;
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

class ArtistListBlock extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $artists = [];
        $artists['DJ'] = $this->em->getRepository(Artist::class)->findBy(['copyForArchive' => false, 'type' => 'DJ'], ['name' => 'ASC']);
        $artists['LIVE'] = $this->em->getRepository(Artist::class)->findBy(['copyForArchive' => false, 'type' => 'LIVE'], ['name' => 'ASC']);
        $artists['VJ'] = $this->em->getRepository(Artist::class)->findBy(['copyForArchive' => false, 'type' => 'VJ'], ['name' => 'ASC']);
        $artists['ART'] = $this->em->getRepository(Artist::class)->findBy(['copyForArchive' => false, 'type' => 'ART'], ['name' => 'ASC']);

        return $this->renderResponse($blockContext->getTemplate(), [
            'block' => $blockContext->getBlock(),
            'artists' => $artists,
            'count' => array_sum(array_map(count(...), $artists)),
            'settings' => $blockContext->getSettings(),
        ], $response);
    }

    public function __construct(Environment $twig, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/artist_list.html.twig',
            'box' => false,
        ]);
    }

    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), $code ?? $this->getName(), null, 'messages', [
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
        return 'Artist List Block';
    }
}
