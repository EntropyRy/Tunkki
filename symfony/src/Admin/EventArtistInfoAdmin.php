<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\Form\Type\DateTimePickerType;

final class EventArtistInfoAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'artists';
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('Artist')
            ->add('SetLength')
            ->add('StartTime')
            ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('Artist')
            ->add('WishForPlayTime')
            ->add('SetLength')
            ->add('StartTime')
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
        $formMapper
            ->add('Artist')
            ->add('WishForPlayTime', TextType::class, ['disabled' => true])
            ->add('SetLength')
            ->add('StartTime', DateTimePickerType::class, [
                'dp_pick_time'=> true,
                'dp_pick_date'=> false,
                'format' => 'H:mm'
            ])
            ;
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('SetLength')
            ->add('StartTime')
            ;
    }
}
