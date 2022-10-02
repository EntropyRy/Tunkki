<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\Service\EditableBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Validator\ErrorElement;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\UrlsType;

class ArtistInfoBlock extends BaseBlockService implements EditableBlockService
{
    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $user = $this->security->getUser();
        $member = $user->getMember();
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'member'    => $member
        ], $response);
    }
    public function configureEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
        $this->configureCreateForm($formMapper, $block);
    }
    public function configureCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function __construct($twig, protected Security $security) //, EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/artist_info.html.twig',
        ]);
    }
    public function getMetadata(): Metadata
    {
        return new Metadata($this->getName(), null, null, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }
    public function validate(ErrorElement $errorElement, BlockInterface $block): void
    {
    }
    public function getName(): string
    {
        return 'Artist Info Block';
    }
}
