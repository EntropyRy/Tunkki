<?php

namespace App\Block;

use App\Entity\User;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\Service\EditableBlockService;
use Sonata\BlockBundle\Form\Mapper\FormMapper;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class ArtistInfoBlock extends BaseBlockService implements EditableBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $user = $this->security->getUser();
        assert($user instanceof User);
        $member = $user->getMember();

        return $this->renderResponse($blockContext->getTemplate(), [
            'block' => $blockContext->getBlock(),
            'settings' => $blockContext->getSettings(),
            'member' => $member,
        ], $response);
    }

    #[\Override]
    public function configureEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
        $this->configureCreateForm($formMapper, $block);
    }

    #[\Override]
    public function configureCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function __construct(Environment $twig, protected Security $security) // , EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/artist_info.html.twig',
        ]);
    }

    #[\Override]
    public function getMetadata(): Metadata
    {
        return new Metadata($this->getName(), null, null, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }

    #[\Override]
    public function validate(ErrorElement $errorElement, BlockInterface $block): void
    {
    }

    public function getName(): string
    {
        return 'Artist Info Block';
    }
}
