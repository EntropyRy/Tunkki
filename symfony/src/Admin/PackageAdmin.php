<?php

namespace App\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

use Sonata\AdminBundle\Form\Type\ModelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class PackageAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('name')
            ->add('rent')
            ->add('whoCanRent')
            ->add('notes');
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('rent')
            ->add('items')
            ->add('rentFromItems')
            ->add('whoCanRent')
            //          ->add('needsFixing')
            ->add('itemsNeedingFixing', 'array')
            ->add('notes')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []]
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $p = $this->getSubject();
        if (is_null($p->getId())) {
            $query = $this->em->createQueryBuilder()->select('i')
                ->from('App:Item', 'i')
                ->andWhere('i.packages is empty')
                ->orderBy('i.name', 'ASC');
        } else {
            $query = $this->em->createQueryBuilder()->select('i')
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
            ->add('whoCanRent', null, ['multiple' => true, 'expanded' => true, 'by_reference' => false, 'help' => 'Select all fitting groups'])
            ->add('items', ModelType::class, [
                'btn_add' => false,
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
                'query' => $query,
                'help' => 'Item cannot be in two packages at the same time'
            ])
            ->add('rentFromItems', TextType::class, ['disabled' => true])
            ->add('rent')
            ->add('compensationPrice')
            ->add('notes')
            ->end();
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('name')
            ->add('items')
            ->add('rent')
            //      ->add('needsFixing')
            ->add('compensationPrice')
            ->add('notes');
    }
    public function __construct(
        protected EntityManagerInterface $em,
    ) {
    }
}
