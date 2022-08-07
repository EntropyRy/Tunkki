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

class StatisticsBlock extends BaseBlockService
{
    protected $security;
    protected $em;

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $memberR = $this->em->getRepository('App:Member');
        $stats['block.stats.members'] = $memberR->countByMember();
        $stats['block.stats.active_members'] = $memberR->countByActiveMember();
        $stats['block.stats.bookings'] = $this->em->getRepository('App:Booking')->countHandled();
        $stats['block.stats.events'] = $this->em->getRepository('App:Event')->countDone();
        $member = $this->security->getUser()->getMember();
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'member'    => $member,
            'stats'     => $stats
        ], $response);
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
        $this->buildCreateForm($formMapper, $block);
    }
    public function buildCreateForm(FormMapper $formMapper, BlockInterface $block)
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

    public function __construct($twig, Security $security, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->security = $security;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'template' => 'block/statistics.html.twig',
        ]);
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-link',
        ]);
    }
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
    }
    public function getName()
    {
        return 'Statistics Block';
    }
}
