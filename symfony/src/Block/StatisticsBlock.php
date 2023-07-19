<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Validator\ErrorElement;
use App\Entity\User;
use App\Repository\MemberRepository;
use App\Repository\BookingRepository;
use App\Repository\EventRepository;
use Twig\Environment;

class StatisticsBlock extends BaseBlockService
{
    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $stats = [];
        $stats['block.stats.members'] = $this->memberR->countByMember();
        $stats['block.stats.active_members'] = $this->memberR->countByActiveMember();
        $stats['block.stats.bookings'] = $this->bookingR->countHandled();
        $stats['block.stats.events'] = $this->eventR->countDone();
        $user = $this->security->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        return $this->renderResponse(
            $blockContext->getTemplate(),
            [
                'block'     => $blockContext->getBlock(),
                'settings'  => $blockContext->getSettings(),
                'member'    => $member,
                'stats'     => $stats
            ],
            $response
        );
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
        $this->buildCreateForm($formMapper, $block);
    }
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
    }

    public function __construct(
        Environment $twig,
        protected \Symfony\Bundle\SecurityBundle\Security $security,
        protected MemberRepository $memberR,
        protected BookingRepository $bookingR,
        protected EventRepository $eventR
    ) {
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'template' => 'block/statistics.html.twig',
            ]
        );
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata(
            $this->getName(),
            ($code ?? $this->getName()),
            null,
            'messages',
            [
                'class' => 'fa fa-link',
            ]
        );
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block): void
    {
    }
    public function getName(): string
    {
        return 'Statistics Block';
    }
}
