<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\User;
use App\Helper\ZMQHelper;
use App\Repository\DoorLogRepository;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

class DoorInfoBlock extends BaseBlockService
{
    #[\Override]
    public function execute(
        BlockContextInterface $blockContext,
        ?Response $response = null,
    ): Response {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            return $this->renderResponse(
                $blockContext->getTemplate(),
                [],
                $response,
            );
        }
        \assert($user instanceof User);
        $member = $user->getMember();
        $now = new \DateTimeImmutable('now');
        $status = null;
        $logs = [];

        try {
            $status = $this->zmq->send(
                'dev init: '.
                    $member->getUsername().
                    ' '.
                    $now->getTimestamp(),
            );
        } catch (\Exception) {
            // ZMQ service might not be available in test environment
            $status = 'Service unavailable';
        }

        try {
            $logs = $this->doorLogR->getLatest(3);
        } catch (\Exception) {
            // Door log repository might fail if table doesn't exist
            $logs = [];
        }

        return $this->renderResponse(
            $blockContext->getTemplate(),
            [
                'block' => $blockContext->getBlock(),
                'settings' => $blockContext->getSettings(),
                'logs' => $logs,
                'member' => $member,
                'status' => $status,
            ],
            $response,
        );
    }

    public function buildEditForm(
        FormMapper $formMapper,
        BlockInterface $block,
    ): void {
        $this->buildCreateForm($formMapper, $block);
    }

    public function buildCreateForm(
        FormMapper $formMapper,
        BlockInterface $block,
    ): void {
    }

    public function __construct(
        Environment $twig,
        protected Security $security,
        protected DoorLogRepository $doorLogR,
        protected ZMQHelper $zmq,
    ) {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/door_info.html.twig',
        ]);
    }

    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata(
            $this->getName(),
            $code ?? $this->getName(),
            null,
            'messages',
            [
                'class' => 'fa fa-link',
            ],
        );
    }

    public function getName(): string
    {
        return 'Door Info Block';
    }
}
