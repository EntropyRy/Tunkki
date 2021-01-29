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

class EmailLists extends BaseBlockService {

    protected $security;
    protected $em;

    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $member = $this->security->getUser()->getMember();
        return $this->renderResponse($blockContext->getTemplate(), [
            'block'     => $blockContext->getBlock(),
            'settings'  => $blockContext->getSettings(),
            'member'    => $member,
        ], $response);
    }
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block) {
        $this->buildCreateForm($formMapper, $block);
    }
	public function buildCreateForm(FormMapper $formMapper, BlockInterface $block) {
/*		$formMapper
			->add('settings', ImmutableArrayType::class, [
				'keys' => [
                ]
            ]);*/
    }

    public function __construct($twig, Security $security, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->security = $security;
        parent::__construct($twig);
    }

    public function configureSettings(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'template' => 'block/email_lists.html.twig',
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
        return 'Email lists Block';
    }

}

