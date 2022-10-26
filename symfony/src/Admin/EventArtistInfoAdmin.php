<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\Form\Type\DateTimePickerType;
use App\Entity\Artist;

final class EventArtistInfoAdmin extends AbstractAdmin
{
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'artists';
    }

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
            ->add('artistDataHasUpdate', null, [
                'template' => 'admin/crud/list__update_artist.html.twig'
            ])
            ->add('ArtistName')
            ->add('ArtistClone.hardware')
            ->add('ArtistClone.linkUrls', FieldDescriptionInterface::TYPE_HTML, [
            ])
            ->add('ArtistClone.genre')
            ->add('ArtistClone.member')
            ->add('WishForPlayTime')
            ->add('freeWord')
            ->add('stage')
            ->add('SetLength')
            ->add('StartTime')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
//                    'show' => [],
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
        if (empty($subject->getArtist()) && empty($subject->getArtistClone())) {
            $formMapper
                ->add('Artist', null, [
                    'query_builder' => fn ($repo) => $repo->createQueryBuilder('a')
                        ->andWhere('a.copyForArchive = :copy')
                        ->setParameter('copy', false),
                    'choice_label' => function (Artist $artist) {
                        return $artist->getGenre() ? $artist->getName().' ('.$artist->getGenre().')' : $artist->getName();
                    },
                ]);
        } else {
            $formMapper
                ->add('Artist', null, ['disabled' => true])
                ->add('ArtistClone', null, ['disabled' => true])
                ->add('WishForPlayTime', TextType::class, ['disabled' => true])
                ->add('SetLength')
                ->add('stage')
                ->add('StartTime', DateTimePickerType::class, [
                    'dp_side_by_side' => true,
                    'format' => 'd.M.y H:mm',
                    'required' => false,
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
        $event = $eventinfo->getEvent();
        $i = 1;
        foreach ($event->getEventArtistInfos() as $info) {
            if ($info->getArtist() == $eventinfo->getArtist()) {
                $i+=1;
            }
        }
        $artistClone = clone $eventinfo->getArtist();
        $artistClone->setMember(null);
        $artistClone->setCopyForArchive(true);
        $artistClone->setName($artistClone->getName().' for '.$eventinfo->getEvent()->getName(). ' #'. $i);
        $eventinfo->setArtistClone($artistClone);
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('delete');
        $collection->add('update', $this->getRouterIdParameter().'/update');
    }
}
