<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

use Sonata\AdminBundle\Form\Type\ModelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Entity\Item;

class PackageAdmin extends AbstractAdmin
{
    /**
     * @param DatagridMapper $datagridMapper
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('rent')
            ->add('whoCanRent')
            ->add('notes')
        ;
    }

    /**
     * @param ListMapper $listMapper
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->add('name')
            ->add('rent')
            ->add('items')
            ->add('rentFromItems')
            ->add('whoCanRent')
  //          ->add('needsFixing')
            ->add('itemsNeedingFixing','array')
            ->add('notes')
            ->add('_action', null, array(
                'actions' => array(
                    'show' => array(),
                    'edit' => array(),
                    'delete' => array(),
                ),
            ))
        ;
    }

    /**
     * @param FormMapper $formMapper
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $p = $this->getSubject();
        $em = $this->modelManager->getEntityManager(Item::class);
        if(is_null($p->getId())){
            $query = $em->createQueryBuilder('i')->select('i')
                    ->from('App:Item', 'i')
                    ->andWhere('i.packages is empty')
                    ->orderBy('i.name', 'ASC');
        } else {
            $query = $em->createQueryBuilder('i')->select('i')
                    ->from('App:Item', 'i')
                    ->andWhere('i.packages is empty')
                    ->leftJoin('i.packages', 'pack')
                    ->orWhere('pack = :p')
                    ->orderBy('i.name', 'ASC')
                    ->setParameter('p', $p);
        }
        $formMapper
            ->with('Package')
            ->add('name')
            ->add('whoCanRent', null, array('multiple'=>true, 'expanded' => true, 'by_reference' => false, 'help' => 'Select all fitting groups'))
            ->add('items', ModelType::class, [
                'btn_add'=> false, 
                'multiple'=>true, 
                'expanded' => false, 
                'by_reference' => false,
                'query' => $query,
                'help' => 'Item cannot be in two packages at the same time'
            ])
            ->add('rentFromItems', TextType::class, array('disabled' => true))
            ->add('rent')
            ->add('compensationPrice')
            ->add('notes')
            ->end()
        ;
    }

    /**
     * @param ShowMapper $showMapper
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('items')
            ->add('rent')
      //      ->add('needsFixing')
            ->add('compensationPrice')
            ->add('notes')
        ;
    }
}
