<?php

namespace App\Block;

use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class FutureEventsBlock extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $repo = $this->em->getRepository(Event::class);
        assert($repo instanceof EventRepository);
        $events = $repo->getFutureEvents();
        $unreleased = $repo->getUnpublishedFutureEvents();

        return $this->renderResponse($blockContext->getTemplate(), [
            'block' => $blockContext->getBlock(),
            'events' => $events,
            'unreleased' => $unreleased,
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
            'template' => 'block/future_events.html.twig',
            'box' => false,
        ]);
    }

    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), $code ?? $this->getName(), null, 'messages', [
            'class' => 'fa fa-bullhorn',
        ]);
    }

    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function getName(): string
    {
        return 'Future Events Block';
    }
}
