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
use Sonata\ClassificationBundle\Form\ChoiceList\CategoryChoiceLoader;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Sonata\ClassificationBundle\Form\Type\CategorySelectorType;
use Application\Sonata\ClassificationBundle\Entity\Category;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ItemAdmin extends AbstractAdmin
{

    protected $mm; // Matteromst helper
    protected $ts;

    /**
     * Default Datagrid values
     *
     * @var array
     */
    protected $datagridValues = array(
        '_page' => 1,            // display the first page (default = 1)
        '_sort_order' => 'DESC', // reverse order (default = 'ASC')
        '_sort_by' => 'updatedAt'  // name of the ordered field
        // the '_sort_by' key can be of the form 'mySubModel.mySubSubModel.myField'.
    );
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $context = 'item';
        $currentContext = $this->getConfigurationPool()->getContainer()->get('sonata.classification.manager.context')->find($context);

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
            ->add('pakages')
            ->add('toSpareParts')
            ->add('needsFixing')
            ->add('category',  null, [
                    'label' => 'Item.category'
                ], CategorySelectorType::class,  [
                    'class' => $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category')->getClass(),
                    'context' =>  $currentContext,
                    'model_manager' => $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category')->getModelManager(),
                    'category' => new Category(),
                    'multiple' => true,
                ]
            )
            ->add('forSale')
            ->add('commission', 'doctrine_orm_date',['field_type'=>'sonata_type_date_picker'])
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
        $currentContext = $this->getConfigurationPool()->getContainer()->get('sonata.classification.manager.context')->find($context);

        $formMapper
//            ->add('id')
        ->tab('General')
            ->with('General Information', array('class' => 'col-md-6'))
                ->add('name')
                ->add('manufacturer')
                ->add('model')
                ->add('serialnumber')
                ->add('placeinstorage')
                ->add('description', 'textarea', array('required' => false, 'label' => 'Item description'))
                ->add('commission', 'sonata_type_date_picker')
                ->add('commissionPrice')
                ->add('tags', 'sonata_type_model_autocomplete', array(
                    'property' => 'name',
                    'multiple' => 'true',
                    'required' => false,
                    'minimum_input_length' => 2
                ))
              ->add('category', 'Sonata\ClassificationBundle\Form\Type\CategorySelectorType', array(
                        'class' => $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category')->getClass(),
                        'required' => false,
                        'by_reference' => false,
                        'context' => $currentContext,
                        'model_manager' => $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category')->getModelManager(),
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
                ->add('rentNotice', 'textarea', array('required' => false))
                ->add('forSale')
            ->end()
            ->with('Condition', array('class' => 'col-md-6'))
                ->add('toSpareParts')
                ->add('needsFixing')
/*                ->add('fixingHistory', 'sonata_type_collection', array(
                        'label' => null, 'btn_add'=>'Add new event', 
                        'by_reference'=>false,
                        'cascade_validation' => true, 
                        'type_options' => array('delete' => false),
                        'required' => false),
                        array('edit'=>'inline'))*/
            //    ->add('history')
            ->end() 
        ->end();
/*        ->tab('Files')
        ->with('Files')
            ->add('files', 'sonata_type_collection', array(
                    'label' => null, 'btn_add'=>'Add new file', 
                    'by_reference'=>false,
                    'cascade_validation' => true, 
                    'type_options' => array('delete' => true),
                    'required' => false),
                    array('edit'=>'inline', 'inline'=>'table'))
        ->end() 
        ->end(); */
        $subject = $this->getSubject();
        if($subject){
            if($subject->getCreatedAt()){
                $formMapper
                    ->tab('Meta')
                    ->with('history')
                    ->add('rentHistory', null, 
                        ['disabled' => true]
                     )
                    ->end()
                    ->with('Meta')
                        ->add('createdAt', 'sonata_type_datetime_picker', array('disabled' => true))
                        ->add('creator', null, array('disabled' => true))
                        ->add('updatedAt', 'sonata_type_datetime_picker', array('disabled' => true))
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
            $menu->addChild('Fixing History', array(
                'uri' => $admin->generateUrl('entropy_tunkki.admin.event.list', array('id' => $id))
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
        $username = $user->getFirstname()." ".$user->getLastname();
        $text = '#### <'.$this->generateUrl('show', ['id'=> $Item->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.$Item->getName().'> created by '.$username;
        $this->mm->SendToMattermost($text);
    }
    public function preUpdate($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
        $Item->setModifier($user);
        $em = $this->getModelManager()->getEntityManager($this->getClass());
        $original = $em->getUnitOfWork()->getOriginalEntityData($Item);
        $text = '#### <'.$this->generateUrl('show', ['id'=> $Item->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.$Item->getName().'> updated';
        if($original['name']!= $Item->getName()) {
            $text .= '; renamed from '.$original['name'];
        }
        if($original['needsFixing'] == false && $Item->getNeedsFixing() == true){
            $text .= '; updeted to be broken';
        }
        elseif($original['needsFixing'] == true && $Item->getNeedsFixing() == false){
            $text .= '; updeted to be fixed';
        }
        $text .= ' by '. $username;
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
    public function __construct($code, $class, $baseControllerName, $mm=null, $ts=null)
    {
        $this->mm = $mm;
        $this->ts = $ts;
        parent::__construct($code, $class, $baseControllerName);
    }
}
