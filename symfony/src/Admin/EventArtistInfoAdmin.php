<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractAdmin<EventArtistInfo>
 *
 * TODO: Verify the actual managed entity FQCN. If the entity class name differs
 * (e.g. EventArtistInfo vs EventArtist or a join entity), update this annotation.
 */
final class EventArtistInfoAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'artists';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('Artist')
            ->add('SetLength')
            ->add('stage')
            ->add('StartTime');
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('ArtistClone.linkUrls', FieldDescriptionInterface::TYPE_HTML, [])
            ->add('Artist.member')
            ->add('WishForPlayTime')
            ->add('freeWord')
            ->add('stage')
            ->add('StartTime')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    //                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                    'update' => [
                        'template' => 'admin/crud/list__action_update_artist.html.twig',
                    ],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $subject = $this->getSubject();
        if (empty($subject->getArtist()) && empty($subject->getArtistClone())) {
            $formMapper
                ->add('Artist', null, [
                    'query_builder' => fn ($repo) => $repo->createQueryBuilder('a')
                        ->andWhere('a.copyForArchive = :copy')
                        ->setParameter('copy', false),
                    'choice_label' => fn (Artist $artist): string => $artist->getGenre() ? $artist->getType().': '.$artist->getName().' ('.$artist->getGenre().')' : $artist->getName(),
                ]);
        } else {
            $formMapper
                ->add('Artist', null, ['disabled' => true])
                ->add('ArtistClone', null, ['disabled' => true])
                ->add('WishForPlayTime', TextType::class, ['disabled' => true])
                ->add('SetLength')
                ->add('stage')
                ->add('StartTime', DateTimePickerType::class, [
                    'format' => 'd.M.y H:mm',
                    'required' => false,
                    'datepicker_options' => [
                        'display' => [
                            'sideBySide' => true,
                            'components' => [
                                'seconds' => false,
                            ],
                        ],
                    ],
                    'help' => 'Please select right date so that we can have right order in the timetable. This also tells the artist they have been chosen to play in their profile page',
                ]);
        }
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('SetLength')
            ->add('StartTime');
    }

    #[\Override]
    public function prePersist($eventinfo): void
    {
        $event = $eventinfo->getEvent();
        $i = 1;
        foreach ($event->getEventArtistInfos() as $info) {
            if ($info->getArtist() == $eventinfo->getArtist()) {
                ++$i;
            }
        }
        $artistClone = clone $eventinfo->getArtist();
        $artistClone->setMember(null);
        $artistClone->setCopyForArchive(true);
        $artistClone->setName($artistClone->getName().' for '.$eventinfo->getEvent()->getName().' #'.$i);
        $eventinfo->setArtistClone($artistClone);
    }

    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('delete');
        $collection->add('update', $this->getRouterIdParameter().'/update');
    }

    #[\Override]
    protected function configureExportFields(): array
    {
        return ['artistClone.name', 'artistClone.genre', 'WishForPlayTime', 'SetLength', 'artistClone.linkUrls', 'freeWord', 'stage', 'StartTime'];
    }
}
