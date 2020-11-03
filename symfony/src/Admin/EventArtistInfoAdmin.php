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
            ->add('Artist')
            ->add('artistClone')
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
                'dp_side_by_side' => true,
                'format' => 'd.M.y H:m',
                'required' => false,
                'help' => 'please select right date so that we can order the artist right. This also tells the artist they have been chosen to play in their profile page'
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
    public function prePersist($eventinfo): void
    {
        $artistClone = clone $eventinfo->getArtist();
        $artistClone->setMember(null);
        $artistClone->setName($artistClone->getName().'#'.$eventinfo->getArtist()->getId());
        $eventinfo->setArtistClone($artistClone);
//        $this->em->persist($artistClone);
//        $this->em->persist($eventinfo);
//        $this->em->flush();
        //$this->addFlash('success', $trans->trans('Artist cloned succesfully'));
    }
}
