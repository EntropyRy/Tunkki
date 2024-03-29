<?php

namespace App\Block;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\Service\EditableBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Validator\ErrorElement;
use Twig\Environment;

class ArtistInfoBlock extends BaseBlockService implements EditableBlockService
{
    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $user = $this->security->getUser();
        assert($user instanceof User);
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

    public function __construct(Environment $twig, protected \Symfony\Bundle\SecurityBundle\Security $security) //, EntityManagerInterface $em)
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
