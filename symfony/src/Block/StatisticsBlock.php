<?php

namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Validator\ErrorElement;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\UrlsType;
use App\Entity\Member;
use App\Entity\Event;
use App\Entity\Booking;

class StatisticsBlock extends BaseBlockService
{
    public function execute(BlockContextInterface $blockContext, Response $response = null): Response
    {
        $stats = [];
        $memberR = $this->em->getRepository(Member::class);
        $stats['block.stats.members'] = $memberR->countByMember();
        $stats['block.stats.active_members'] = $memberR->countByActiveMember();
        $stats['block.stats.bookings'] = $this->em->getRepository(Booking::class)->countHandled();
        $stats['block.stats.events'] = $this->em->getRepository(Event::class)->countDone();
        $member = $this->security->getUser()->getMember();
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'member'    => $member,
            'stats'     => $stats
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
                            ['title', TextType::class, [
                                'label' => 'List Title',
                            ]],
                            ['show', ChoiceType::class,[
                                'choices' => [
                                    'Everybody can see this' => false,
                                    'Show only to logged in user' => true,
                                ]
                            ]],
                            ['urls', CollectionType::class, [
                                'required' => false,
                                'allow_add' => true,
                                'allow_delete' => true,
                                'prototype' => true,
                                'by_reference' => false,
                                'allow_extra_fields' => true,
                                'entry_type' => UrlsType::class,
                            ]],
                        ]
                    ]);*/
    }

    public function __construct($twig, protected Security $security, protected EntityManagerInterface $em)
    {
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'template' => 'block/statistics.html.twig',
        ]);
    }
    public function getBlockMetadata($code = null): Metadata
    {
        return new Metadata($this->getName(), ($code ?? $this->getName()), false, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block): void
    {
    }
    public function getName(): string
    {
        return 'Statistics Block';
    }
}
