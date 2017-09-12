<?php

namespace Entropy\TunkkiBundle\Admin;

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

class ItemAdmin extends AbstractAdmin
{

    protected $mm;
    protected $ts;
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
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
            ->add('rentNotice')
            ->add('toSpareParts')
            ->add('needsFixing')
/*            ->add('category',  'doctrine_orm_choice', [
                    'label' => 'Category'
                ], CategorySelectorType::class,  [
                    'class' => 'Application\Sonata\ClassificationBundle\Entity\Category',
//                    'context' =>  'item',
                    'model_manager' => $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category')->getModelManager(),
                    'category' => new Category(),
                ]
            )*/
            ->add('category', null, array('field_options' => array('expanded' => false, 'multiple' => true)) )
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
            ->add('tags')
            ->add('rent', 'currency', array(
                'currency' => 'Eur'
                ))
            ->add('placeinstorage')
            ->add('needsFixing', null, array('editable'=>true, 'template' => 'EntropyTunkkiBundle:Admin:invertboolean.html.twig'))
//            ->add('rentHistory')
//            ->add('history')
//            ->add('forSale', null, array('editable'=>true))
//            ->add('createdAt')
//            ->add('updatedAt')
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
            ->with('Item', array('class' => 'col-md-6'))
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
                        'category' => $this->getSubject()->getCategory() ? $this->getSubject()->getCategory() : new Category,
                        'placeholder' => $this->getSubject()->getCategory(),
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
            ->with('Condition')
                ->add('toSpareParts')
                ->add('needsFixing')
                ->add('fixingHistory', 'sonata_type_collection', array(
                        'label' => null, 'btn_add'=>'Add new event', 
                        'by_reference'=>false,
                        'cascade_validation' => true, 
                        'type_options' => array('delete' => false),
                        'required' => false),
                        array('edit'=>'inline', 'inline'=>'table'))
            //    ->add('history')
            ->end() 
        ->end()
        ->tab('Files')
        ->with('Files')
            ->add('files', 'sonata_type_collection', array(
                    'label' => null, 'btn_add'=>'Add new file', 
                    'by_reference'=>false,
                    'cascade_validation' => true, 
                    'type_options' => array('delete' => true),
                    'required' => false),
                    array('edit'=>'inline', 'inline'=>'table'))
        ->end() 
        ->end();
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
            ->add('whoCanRent', 'choice', array(
                 'choices'=> array(
                              '1' => 'Everybody', '2' => 'Nobody', 
                              '3' => 'Members', '4' => 'Organizations'
                     ),
                     'multiple' => true
            ))
            ->add('tags')
            ->add('rent')
            ->add('rentNotice')
            ->add('needsFixing')
            ->add('rentHistory')
            ->add('history')
            ->add('forSale')
            ->add('files.fileinfo')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator')
            ->add('modifier')
        ;
    }
    public function prePersist($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $Item->setModifier($user);
        $Item->setCreator($user);
        foreach ($Item->getfixingHistory() as $history) {
            if($history->getCreator()==''){ 
                $history->setCreator($user);
            }
            if($history->getModifier()==''){ 
                $history->setModifier($user);
            }
        } 
    }
    public function postPersist($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
        $text = '#### <'.$this->generateUrl('show', ['id'=> $Item->getId()]).'|'.$Item->getName().'> created by '.$username;
        $this->mm->SendToMattermost($text);
    }
    public function preUpdate($Item)
    {
        $user = $this->ts->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
        $Item->setModifier($user);
        foreach ($Item->getfixingHistory() as $history) {
            if($history->getCreator()==''){ 
                $history->setCreator($user);
            }
            if($history->getModifier()==''){ 
                $history->setModifier($user);
            }
        }
        $em = $this->getModelManager()->getEntityManager($this->getClass());
        $original = $em->getUnitOfWork()->getOriginalEntityData($Item);
        if($original['needsFixing'] == false && $Item->getNeedsFixing() == true){
            $text = '#### <'.$this->getConfigurationPool()->getContainer()->get('request')->getSchemeAndHttpHost().
                    ''.$this->generateUrl('show', ['id'=> $Item->getId()]).'|'.
                    $Item->getName().'> updeted to be broken by '.$username;
        }
        elseif($original['needsFixing'] == true && $Item->getNeedsFixing() == false){
            $text = '#### <'.$this->getConfigurationPool()->getContainer()->get('request')->getSchemeAndHttpHost().
                    ''.$this->generateUrl('show', ['id'=> $Item->getId()]).'|'.
                    $Item->getName().'> updeted to be fixed by '.$username;
        }
        $this->mm->SendToMattermost($text);
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('clone', $this->getRouterIdParameter().'/clone');
    }

    public function __construct($code, $class, $baseControllerName, $mm=null, $ts=null)
    {
        $this->mm = $mm;
        $this->ts = $ts;
        parent::__construct($code, $class, $baseControllerName);
    }
}
