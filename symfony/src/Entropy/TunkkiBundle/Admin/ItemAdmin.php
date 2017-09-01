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
            ->add('category', null, array('field_options' => array('expanded' => false, 'multiple' => true)) )
            //->add('rentHistory')
            //->add('history')
            ->add('forSale')
            ->add('commission')
            //->add('createdAt')
            //->add('updatedAt')
            //->add('creator')
            //->add('modifier')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
//            ->add('id')
            ->addIdentifier('name')
//            ->add('manufacturer')
 //           ->add('model')
 //           ->add('description')
 //           ->add('whoCanRent')
            ->add('tags')
         /*   ->add('status', 'choice', array(
                'multiple' => true,
                'choices'=>
                    array(
                          'OK' => 'OK', 'Rikki' => 'Rikki', 'Ei voi korjata' => 'Ei voi korjata', 
                          'Puutteellinen' => 'Puutteellinen', 'Kateissa' => 'Kateissa'
                         )
                 ))*/
            ->add('rent', 'currency', array(
                'currency' => 'Eur'
                ))
            //->add('rentNotice')
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
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
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
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
        $username = $user->getFirstname()." ".$user->getLastname();
        $this->SendToMattermost($Item, $username, 'created');
    }
    public function preUpdate($Item)
    {
        $user = $this->getConfigurationPool()->getContainer()->get('security.token_storage')->getToken()->getUser();
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
            $text = 'updeted to be broken';
        }
        if($original['needsFixing'] == true && $Item->getNeedsFixing() == false){
            $text = 'updated to be fixed';
        }
        $this->SendToMattermost($Item, $username, $text);
    }
    public function SendToMattermost($Item, $username, $text)
    {
        $xcURL = $this->getConfigurationPool()->getContainer()->getParameter('mm_tunkki_hook');
        $botname = $this->getConfigurationPool()->getContainer()->getParameter('mm_tunkki_botname');
        $botimg = $this->getConfigurationPool()->getContainer()->getParameter('mm_tunkki_img');
        $add_url = $this->getConfigurationPool()->getContainer()->getParameter('mm_add_url');
        
        $curl = curl_init($xcURL);
        $payload = '{"username":"'.$botname.'", "icon_url":"'.$botimg.'",
            "text":"#### <'
                .$add_url.'/'.$Item->getId().'/show|'.$Item->getName().'> '.$text.' by '.$username.'"}';
        $cOptArr = array (
            CURLOPT_URL => $xcURL,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1
        );
        $rc = curl_setopt_array ($curl, $cOptArr);
        $rc = curl_setopt ($curl, CURLOPT_POSTFIELDS, http_build_query (array ('payload' => $payload)));
        $rc = curl_exec ($curl);
        if ($rc == false){
            curl_close ($curl);
        }
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('clone', $this->getRouterIdParameter().'/clone');
    }

    public function __construct($code, $class, $baseControllerName, $categoryManager=null)
    {
        parent::__construct($code, $class, $baseControllerName);
    }
}
