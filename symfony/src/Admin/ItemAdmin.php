<?php

namespace App\Admin;

use Knp\Menu\ItemInterface as MenuItemInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use App\Application\Sonata\ClassificationBundle\Entity\Category;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Sonata\ClassificationBundle\Form\ChoiceList\CategoryChoiceLoader;
use Sonata\ClassificationBundle\Form\Type\CategorySelectorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\Form\Type\DateTimePickerType;
use Sonata\Form\Type\DatePickerType;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;

class ItemAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'item'; 
    
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
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
            ->add('forSale')
            ->add(
                'category',
                null,
                [
                    'label' => 'Category'
                ],
                CategorySelectorType::class
            )
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('name')
            ->add('rent', 'currency', ['currency' => 'Eur'])
            ->add('needsFixing', null, ['editable'=>true, 'inverse' => true])
//            ->add('rentHistory')
//            ->add('history')
//            ->add('forSale', null, array('editable'=>true))
//            ->add('createdAt')
            ->add('updatedAt')
//            ->add('creator')
            ->add('_action', null, ['actions' => ['status' => ['template' => 'admin/crud/list__action_status.html.twig'], 'clone' => ['template' => 'admin/crud/list__action_clone.html.twig'], 'show' => [], 'edit' => [], 'delete' => []]])
        ;
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $context = 'item';
        $currentContext = $this->cm->find($context);
        $categoryAdmin = $this->getConfigurationPool()->getAdminByAdminCode('sonata.classification.admin.category');

        $formMapper
        ->tab('General')
            ->with('General Information', ['class' => 'col-md-6'])
                ->add('name', null, ['help' => 'used in spoken language'])
                ->add('manufacturer')
                ->add('model')
                ->add('serialnumber')
                ->add('placeinstorage')
                ->add('url')
                ->add('description', TextareaType::class, ['required' => false, 'label' => 'Item description'])
                ->add('commission', DatePickerType::class, [
                    'required' => false,
                    'format' => 'd.M.y'
                ])
                ->add('purchasePrice')
                ->add('tags', ModelAutocompleteType::class, [
                    'property' => 'name',
                    'multiple' => 'true',
                    'required' => false,
                    'minimum_input_length' => 2
                ])
              ->add('category', CategorySelectorType::class, [
                        'class' => $categoryAdmin->getClass(),
                        'required' => false,
                        'by_reference' => false,
                        'context' => $currentContext,
                        'model_manager' => $categoryAdmin->getModelManager(),
                        'category' => new Category(),
                        'btn_add' => false
                    ])
            ->end()
            ->with('Rent Information', ['class' => 'col-md-6'])
                ->add('whoCanRent', null, [
                    'multiple' => true,
                    'expanded' => true,
                    'help' => 'Select all fitting groups!'
                ])
                ->add('rent', null, ['label' => 'Rental price (€)'])
                ->add('rentNotice', TextareaType::class, ['required' => false,'label' => 'Rental Notice'])
                ->add('compensationPrice', null, ['label' => 'Compensation price (€)'])
            ->end()
/*            ->with('Condition', array('class' => 'col-md-6'))
                ->add('forSale')
                ->add('toSpareParts')
                ->add('needsFixing', null, ['disabled' => true, 'help' => 'to change this use the fixing history'])
                ->end() */
        ->end();
        $subject = $this->getSubject();
        if ($subject) {
            if ($subject->getCreatedAt()) {
                $formMapper
                    ->tab('Meta')
                    ->with('history')
                    ->add('rentHistory', null, ['disabled' => true])
                    ->end()
                    ->with('Meta')
                        ->add('createdAt', DateTimePickerType::class, ['disabled' => true])
                        ->add('creator', null, ['disabled' => true])
                        ->add('updatedAt', DateTimePickerType::class, ['disabled' => true])
                        ->add('modifier', null, ['disabled' => true])
                    ->end()
                ;
            }
        }
    }

    protected function configureShowFields(ShowMapper $showMapper): void
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
    protected function configureSideMenu(MenuItemInterface $menu, $action, AdminInterface $childAdmin = null): void
    {
        if (!$childAdmin && !in_array($action, ['edit', 'show'])) {
            return;
        }

        $admin = $this->isChild() ? $this->getParent() : $this;
        $id = $admin->getRequest()->get('id');

//        $menu->addChild('View Item', array('uri' => $admin->generateUrl('show', array('id' => $id))));

        if ($this->isGranted('EDIT')) {
            $menu->addChild('Edit Item', ['uri' => $admin->generateUrl('edit', ['id' => $id])]);
            $menu->addChild('Status', ['uri' => $admin->generateUrl('entropy_tunkki.admin.statusevent.create', ['id' => $id])]);
            $menu->addChild('Files', ['uri' => $admin->generateUrl('entropy_tunkki.admin.file.list', ['id' => $id])]);
        }
    }
    public function prePersist($Item): void
    {
        $user = $this->ts->getToken()->getUser();
        $Item->setModifier($user);
        $Item->setCreator($user);
    }
    public function postPersist($Item): void
    {
        $user = $this->ts->getToken()->getUser();
        $text = 'ITEM: <'.$this->generateUrl('show', ['id'=> $Item->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.$Item->getName().'> created by '.$user;
        $this->mm->SendToMattermost($text, 'vuokraus');
    }
    public function preUpdate($Item): void
    {
        $user = $this->ts->getToken()->getUser();
        $Item->setModifier($user);
        $em = $this->getModelManager()->getEntityManager($this->getClass());
        $original = $em->getUnitOfWork()->getOriginalEntityData($Item);
        $text = 'ITEM: <'.$this->generateUrl('show', ['id'=> $Item->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'|'.$Item->getName().'>:';
        if ($original['name']!= $Item->getName()) {
            $text .= ' renamed from '.$original['name'];
            $text .= ' by '. $user;
            $this->mm->SendToMattermost($text, 'vuokraus');
        }
    }
    public function preRemove($Item): void
    {
        $user = $this->ts->getToken()->getUser();
        $text = '#### ITEM: '.$Item->getName().' deleted by '.$user;
        $this->mm->SendToMattermost($text, 'vuokraus');
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->add('clone', $this->getRouterIdParameter().'/clone');
    }
    public function configureBatchActions(array $actions): array
    {
        if ($this->hasRoute('edit') && $this->hasAccess('edit')) {
            $actions['batchEdit'] = ['ask_confirmation' => true];
        }
        return $actions;
    }
    public function __construct($code, $class, $baseControllerName, protected $mm=null, protected $ts=null, protected $cm=null)
    {
        parent::__construct($code, $class, $baseControllerName);
    }
}
