<?php

namespace App\Block;

use App\Repository\DoorLogRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use App\Helper\ZMQHelper;
use App\Entity\User;
use Twig\Environment;

class DoorInfoBlock extends BaseBlockService
{
    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $user = $this->security->getUser();
        if (is_null($user)) {
            return $this->renderResponse($blockContext->getTemplate(), [], $response);
        }
        assert($user instanceof User);
        $member = $user->getMember();
        $now = new \DateTime('now');
        $status = $this->zmq->send('dev' . ' init: ' . $member->getUsername() . ' ' . $now->getTimestamp());
        $logs = $this->doorLogR->getLatest(3);
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'logs'    => $logs,
            'member'    => $member,
            'status'    => $status
        ], $response);
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
        protected DoorLogRepository $doorLogR,
        protected ZMQHelper $zmq
    ) {
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/door_info.html.twig',
        ]);
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), ($code ?? $this->getName()), false, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }
    public function getName(): string
    {
        return 'Door Info Block';
    }
}
