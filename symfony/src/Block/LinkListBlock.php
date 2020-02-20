<?php
namespace App\Block;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\Service\AbstractAdminBlockService as BaseBlockService;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\Templating\EngineInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Validator\ErrorElement;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Form\Type\CollectionType;
use App\Form\UrlsType;
/**
 * @author H
 */
class LinkListBlock extends BaseBlockService {

    protected $em;
    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings()
        ], $response);
    }
	public function buildEditForm(FormMapper $formMapper, BlockInterface $block) {
		$formMapper
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
            ]);
    }

    public function __construct($name, EngineInterface $templating, EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct($name, $templating);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'title' => null,
            'show' => false,
            'urls' => null,
            'template' => 'block/links.html.twig',
        ]);
    }
    public function getBlockMetadata($code = null)
    {
        return new Metadata($this->getName(), (null !== $code ? $code : $this->getName()), false, 'messages', [
            'class' => 'fa fa-url',
        ]);
    }
}

