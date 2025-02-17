<?php

declare(strict_types=1);

namespace App\Admin;

use Doctrine\ORM\QueryBuilder;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\DoctrineORMAdminBundle\Filter\NullFilter;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

final class NakkiBookingAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('nakki');
        if (!$this->isChild()) {
            $filter
                ->add('event');
        }
        $filter
            ->add('member')
            ->add('member.isActiveMember')
            ->add('memberNotAssigned', NullFilter::class, [
                'field_name' => 'member',
            ])
            ->add('startAt')
            ->add('startAtRange', DateTimeRangeFilter::class, [
                'field_name' => 'startAt',
            ])
            ->add('endAt')
            ->add('display_only_unique_members', CallbackFilter::class, [
                // This option accepts any callable syntax.
                // 'callback' => [$this, 'getWithOpenCommentFilter'],
                'callback' => static function (ProxyQueryInterface $query, string $alias, string $field, FilterData $data): bool {
                    if (!$data->hasValue()) {
                        return false;
                    }

                    assert($query instanceof QueryBuilder);
                    $query
                        ->groupBy('o.member')
                    ;

                    return true;
                },
                'field_type' => CheckboxType::class,
            ]);
        ;
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('nakki');
        if (!$this->isChild()) {
            $list
                ->add('event');
        }
        $list
            ->add('member')
            ->add('memberHasEventTicket')
            ->add('startAt')
            ->add('endAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('nakki');
        if (!$this->isChild()) {
            $form
                ->add('event');
        }
        $form
            ->add('member')
            ->add('startAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
            ])
            ->add('endAt', DateTimePickerType::class, [
                'input' => 'datetime_immutable',
            ]);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('nakki')
            ->add('event')
            ->add('member')
            ->add('startAt')
            ->add('endAt');
    }
}
