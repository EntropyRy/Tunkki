<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class EmailLists extends BaseBlockService
{
    #[\Override]
    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();

        return $this->renderResponse($blockContext->getTemplate(), [
            'block' => $blockContext->getBlock(),
            'settings' => $blockContext->getSettings(),
            'member' => $member,
        ], $response);
    }

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
        $this->buildCreateForm($formMapper, $block);
    }

    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block): void
    {
        /*		$formMapper
                    ->add('settings', ImmutableArrayType::class, [
                        'keys' => [
                        ]
                    ]);*/
    }

    public function __construct(Environment $twig, protected Security $security, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    #[\Override]
    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/email_lists.html.twig',
        ]);
    }

    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), $code ?? $this->getName(), null, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }

    public function validateBlock(ErrorElement $errorElement, BlockInterface $block): void
    {
    }

    public function getName(): string
    {
        return 'Email lists Block';
    }
}
