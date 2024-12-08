<?php

namespace App\Block;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Sonata\BlockBundle\Meta\Metadata;
use App\Repository\TicketRepository;
use Twig\Environment;

class TicketsBlock extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $user = $this->security->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $tickets = $this->tRepo->findMemberTickets($member);
        return $this->renderResponse($blockContext->getTemplate(), [
            'block' => $blockContext->getBlock(),
            'tickets' => $tickets,
            'settings' => $blockContext->getSettings()
        ], $response);
    }

    public function __construct(
        Environment $twig,
        protected readonly TicketRepository $tRepo,
        protected readonly Security $security
    ) {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/tickets.html.twig',
        ]);
    }
    public function getBlockMetadata(): Metadata
    {
        return new Metadata($this->getName(), null, null, 'messages', [
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
        return 'Tickets Block';
    }
}
