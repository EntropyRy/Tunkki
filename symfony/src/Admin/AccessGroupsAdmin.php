<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class AccessGroupsAdmin extends AbstractAdmin
{
    protected $bag;

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('name')
            ->add('users')
            ->add('roles')
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('active', null, ['editable' => true])
            ->add('users')
            ->add('roles')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $roles = $this->bag->get('security.role_hierarchy.roles');
        $rolesChoices = self::flattenRoles($roles);
        $formMapper
            ->add('name')
            ->add('active')
            ->add('users')
            ->add('roles', ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices'  => $rolesChoices,
            ])

        ;
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('name')
            ->add('users')
            ->add('roles')
        ;
    }

    public function __construct($code, $class, $baseControllerName, ParameterBagInterface $bag=null)
    {
        $this->bag = $bag;
        parent::__construct($code, $class, $baseControllerName);
    }
    /**
     * Turns the role's array keys into string <ROLES_NAME> keys.
     */
    protected static function flattenRoles($rolesHierarchy)
    {
        $flatRoles = array();
        foreach ($rolesHierarchy as $key => $roles) {
            if (empty($roles)) {
                continue;
            }
            if ($key == 'ROLE_ADMIN' || $key == 'ROLE_SUPER_ADMIN') {
                continue;
            }
            $flatRoles[$key] = $key;
        }
        return $flatRoles;
    }
}
