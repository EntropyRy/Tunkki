<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;

final class EmailAdmin extends AbstractAdmin
{
    protected $baseRoutePattern = 'email';
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('purpose')
            ->add('subject')
            ->add('body')
            ;
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('purpose')
            ->add('subject')
            ->add('body', 'html')
            ->add('updatedAt', 'datetime')
            ->add('_action', null, [
                'actions' => [
                    'preview' => ['template' => 'admin/crud/list__action_email_preview.html.twig'],
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('purpose', ChoiceType::class,[
                'choices' => [
                    'Automatic email to new Member on registration' => 'member',
                    'Automatic thank you email to member who requests Active Member status' => 'active_member',
                    'New Active Member info package (can be sent from the member list)' => 'active_member_info_package',
                    //'Booking Email' => 'booking',
                    //'Other' => 'other'
                ], 
                'expanded' => true, 
                'multiple' => false,
                'help' => 'There is also automatic Booking email to vuokra list and "application rejected" for active member (sent from member list). these cannot be edited here. Other kinds of emails can be defined.'
            ])
            ->add('subject', null, ['help' => 'start by "[Entropy]"?'])
            ->add('body', SimpleFormatterType::class, ['format' => 'richhtml'])
            ->add('addLoginLinksToFooter', null, ['help' => 'adds links to tunkki login, usually not needed in emails defined here'])
            ;
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('purpose')
            ->add('subject')
            ->add('body', 'html')
            ->add('addLoginLinksToFooter')
            ->add('createdAt')
            ->add('updatedAt')
            ;
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('preview', $this->getRouterIdParameter().'/preview');
    }
}
