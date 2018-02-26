<?php

namespace Entropy\TunkkiBundle\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Application\Sonata\ClassificationBundle\Entity\Category;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Sonata\ClassificationBundle\Form\ChoiceList\CategoryChoiceLoader;
use Sonata\ClassificationBundle\Form\Type\CategorySelectorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\CoreBundle\Form\Type\CollectionType;
use Sonata\CoreBundle\Form\Type\DateTimePickerType;
use Sonata\CoreBundle\Form\Type\DatePickerType;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;

class ItemAdmin extends AbstractAdmin
{

    protected $mm; // Mattermost helper
    protected $ts; // Token Storage
    protected $cm; // Context Manager

    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $context = 'item';
        $currentContext = $this->cm->find($context);
		$categoryAdmin = $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category');

        $datagridMapper
            ->add('name')
            ->add('manufacturer')
            ->add('model')
            ->add('serialnumber')
            ->add('description')
            ->add('placeinstorage')
            ->add('whoCanRent')
            ->add('tags')
            ->add('rent')
            ->add('packages')
            ->add('toSpareParts')
            ->add('needsFixing')
            ->add('cannotBeRented')
            ->add('category',  null, [
                    'label' => 'Item.category'
                ], CategorySelectorType::class,  [
                    'class' => $categoryAdmin->getClass(),
                    'context' =>  $currentContext,
                    'model_manager' => $categoryAdmin->getModelManager(),
                    'category' => new Category(),
                    'multiple' => true,
                ]
            )
            ->add('forSale')
            ->add('commission', 'doctrine_orm_date',['field_type'=>DateTimePickerType::class])
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('name')
            ->add('rent', 'currency', array(
                'currency' => 'Eur'
                ))
            ->add('needsFixing', null, array('editable'=>true, 'inverse' => true))
//            ->add('rentHistory')
//            ->add('history')
//            ->add('forSale', null, array('editable'=>true))
//            ->add('createdAt')
            ->add('updatedAt')
//            ->add('creator')
            ->add('_action', null, array(
                'actions' => array(
                    'clone' => array(
                        'template' => 'EntropyTunkkiBundle:CRUD:list__action_clone.html.twig'
                    ),
                    'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                )
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $context = 'item';
        $currentContext = $this->cm->find($context);
		$categoryAdmin = $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category');

        $formMapper
        ->tab('General')
            ->with('General Information', array('class' => 'col-md-6'))
                ->add('name')
                ->add('manufacturer')
                ->add('model')
                ->add('serialnumber')
                ->add('placeinstorage')
                ->add('url')
                ->add('description', TextareaType::class, array('required' => false, 'label' => 'Item description'))
				->add('commission', DatePickerType::class, [
					'required' => false, 
					'format' => 'd.M.y'
				])
                ->add('commissionPrice')
                ->add('tags', ModelAutocompleteType::class, array(
                    'property' => 'name',
                    'multiple' => 'true',
                    'required' => false,
                    'minimum_input_length' => 2
                ))
              ->add('category', CategorySelectorType::class, array(
                        'class' => $categoryAdmin->getClass(),
                        'required' => false,
                        'by_reference' => false,
                        'context' => $currentContext,
                        'model_manager' => $categoryAdmin->getModelManager(),
                        'category' => new Category(),
                        'btn_add' => false
                    ))
            ->end()
            ->with('Rent Information', array('class' => 'col-md-6'))
                ->add('whoCanRent', null, array(
                    'multiple' => true, 
                    'expanded' => true,
                    'help' => 'Select all fitting groups!'
                ))
                ->add('rent')
                ->add('rentNotice', TextareaType::class, array('required' => false))
            ->end()
/*            ->with('Condition', array('class' => 'col-md-6'))
                ->add('forSale')
                ->add('toSpareParts')
                ->add('needsFixing', null, ['disabled' => true, 'help' => 'to change this use the fixing history'])
				->end() */
        ->end();
        $subject = $this->getSubject();
        if($subject){
            if($subject->getCreatedAt()){
                $formMapper
                    ->tab('Meta')
                    ->with('history')
                    ->add('rentHistory', null, ['disabled' => true])
                    ->end()
                    ->with('Meta')
                        ->add('createdAt', DateTimePickerType::class, array('disabled' => true))
                        ->add('creator', null, array('disabled' => true))
                        ->add('updatedAt', DateTimePickerType::class, array('disabled' => true))
                        ->add('modifier', null, array('disabled' => true))
                    ->end()
                    ;
            }
        }
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('manufacturer')
            ->add('model')
            ->add('serialnumber')
            ->add('description')
            ->add('commission')
            ->add('whoCanRent')
            ->add('tags')
            ->add('rent')
            ->add('rentNotice')
            ->add('needsFixing')
            ->add('cannotBeRented')
            ->add('rentHistory')
            ->add('history')
            ->add('forSale')
            ->add('file.fileinfo')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator')
            ->add('modifier')
        ;
    }
    protected function configureSideMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null)
    {
        if (!$childAdmin && !in_array($action, array('edit', 'show'))) {
            return;
        }

        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

//        $menu->addChild('View Item', array('uri' => $admin->generateUrl('show', array('id' => $id))));

        if ($this->isGranted('EDIT')) {
            $menu->addChild('Edit Item', array('uri' => $admin->generateUrl('edit', array('id' => $id))));
            $menu->addChild('Manage Status', array(
                'uri' => $admin->generateUrl('entropy_tunkki.admin.event.create', array('id' => $id))
            ));
            $menu->addChild('Manage Files', array(
                'uri' => $admin->generateUrl('entropy_tunkki.admin.file.list', array('id' => $id))
            ));
        }
    }
    public function prePersist($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $Item->setModifier($user);
        $Item->setCreator($user);
    }
    public function postPersist($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $text = 'ITEM: <'.$this->generateUrl('show', ['id'=> $Item->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.$Item->getName().'> created by '.$user;
        $this->mm->SendToMattermost($text);
	}
    public function preUpdate($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $Item->setModifier($user);
        $em = $this->getModelManager()->getEntityManager($this->getClass());
        $original = $em->getUnitOfWork()->getOriginalEntityData($Item);
        $text = 'ITEM: <'.$this->generateUrl('show', ['id'=> $Item->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.$Item->getName().'>:';
        if($original['name']!= $Item->getName()) {
            $text .= ' renamed from '.$original['name'];
			$text .= ' by '. $user;
			$this->mm->SendToMattermost($text);
        }
/*        if($original['needsFixing'] == false && $Item->getNeedsFixing() == true){
            $text .= ' **_BROKEN_**';
			$text .= ' by '. $user;
			$this->mm->SendToMattermost($text);
        }
        elseif($original['needsFixing'] == true && $Item->getNeedsFixing() == false){
            $text .= ' **_FIXED_**';
			$text .= ' by '. $user;
			$this->mm->SendToMattermost($text);
        }
 */
    }
	public function preRemove($Item)
	{
		$user = $this->ts->getToken()->getUser();
        $text = '#### ITEM: '.$Item->getName().' deleted by '.$user;
		$this->mm->SendToMattermost($text);
	}
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('clone', $this->getRouterIdParameter().'/clone');
    }
    public function configureBatchActions($actions)
    {
        if ($this->hasRoute('edit') && $this->hasAccess('edit')) {
            $actions['batchEdit'] = array(
                'ask_confirmation' => true
            );
        }
        return $actions;
    }
    public function __construct($code, $class, $baseControllerName, $mm=null, $ts=null, $cm)
    {
        $this->mm = $mm;
        $this->ts = $ts;
        $this->cm = $cm;
        parent::__construct($code, $class, $baseControllerName);
    }
}
