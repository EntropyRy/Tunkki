<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\AccessGroups;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @extends AbstractAdmin<AccessGroups>
 */
final class AccessGroupsAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('name')
            ->add('users')
            ->add('roles')
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('name')
            ->add('active', null, ['editable' => true])
            ->add('users')
            ->add('roles')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $rolesChoices = $this->getRoleChoices();
        $formMapper
            ->add('name')
            ->add('active')
            ->add('users')
            ->add('roles', ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $rolesChoices,
            ])

        ;
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('name')
            ->add('users')
            ->add('roles')
        ;
    }

    public function __construct(
        protected ParameterBagInterface $bag,
        protected RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    /**
     * @return array<string, string>
     */
    private function getRoleChoices(): array
    {
        $rolesHierarchy = $this->bag->get('security.role_hierarchy.roles');
        $rootRoles = array_keys($rolesHierarchy);
        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($rootRoles);

        $choices = [];
        foreach ($reachableRoles as $role) {
            if ('ROLE_ADMIN' === $role || 'ROLE_SUPER_ADMIN' === $role) {
                continue;
            }
            $choices[$role] = $role;
        }

        ksort($choices);

        return $choices;
    }
}
