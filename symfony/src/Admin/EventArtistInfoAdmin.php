<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollection;

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
            ->add('stage')
            ->add('StartTime')
        ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('Artist')
            ->add('Artist.updatedAt')
            ->add('artistClone.updatedAt')
            ->add('Artist.member')
            ->add('WishForPlayTime')
            ->add('freeWord')
            ->add('stage')
            ->add('SetLength')
            ->add('StartTime')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                    'update' => [
                        'template' => 'admin/crud/list__action_update_artist.html.twig'
                    ],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $subject = $this->getSubject();
        if (empty($subject->getArtist())) {
            $formMapper
                ->add('Artist', null, [
                    'query_builder' => function ($repo) {
                        return $repo->createQueryBuilder('a')
                            ->andWhere('a.copyForArchive = :copy')
                            ->setParameter('copy', false);
                    }
                ]);
        } else {
            $formMapper
                ->add('Artist', null, ['disabled' => true])
                ->add('WishForPlayTime', TextType::class, ['disabled' => true])
                ->add('SetLength')
                ->add('stage')
                ->add('StartTime', DateTimePickerType::class, [
                    'dp_side_by_side' => true,
                    'format' => 'd.M.y H:mm',
                    'required' => true,
                    'help' => 'Please select right date so that we can have right order in the timetable. This also tells the artist they have been chosen to play in their profile page'
                ])
            ;
        }
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('SetLength')
            ->add('StartTime')
        ;
    }
    public function prePersist($eventinfo): void
    {
        $artistClone = clone $eventinfo->getArtist();
        $artistClone->setMember(null);
        $artistClone->setCopyForArchive(true);
        $artistClone->setName($artistClone->getName().' for '.$eventinfo->getEvent()->getName());
        $eventinfo->setArtistClone($artistClone);
    }
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('update', $this->getRouterIdParameter().'/update');
    }
}
