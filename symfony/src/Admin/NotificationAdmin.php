<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\FormatterBundle\Form\Type\SimpleFormatterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

final class NotificationAdmin extends AbstractAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('event')
            ->add('updatedAt')
            ->add('sentAt');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('message', null, [
            ])
            ->add('updatedAt')
            ->add('sentAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'send' => [
                        'template' => 'admin/event/button_announce_telegram.html.twig'
                    ],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('locale', ChoiceType::class, [
                'choices' => [
                    'Finnish' => 'fi',
                    'English' => 'en'
                ],
                'help' => 'Defines links language'
            ])
            ->add(
                'message',
                SimpleFormatterType::class,
                [
                    'format' => 'richhtml',
                    'required' => true,
                    'attr' => ['rows' => 20],
                    'ckeditor_context' => 'simple',
                    'help' => 'Telegram does not support lists of any kind'
                ]
            )
            ->add('options', ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Add event picture to the message header (can be added only on first send)' => 'add_event_picture',
                    'Preview link That is in the content (only if there is no header picture)' => 'add_preview_link',
                    'Send Notification to everybody in the info channel (only works on first send)' => 'send_notification',
                    'Add Event Button' => 'add_event_button',
                    'Add Nakkikone Button' => 'add_nakkikone_button',
                    'Add Buy Ticket Button' => 'add_shop_button'
                ]
            ]);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('message')
            ->add('event')
            ->add('updatedAt')
            ->add('sentAt')
            ->add('messageId');
    }
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('show');
        $collection->remove('delete');
        $collection->add('send', $this->getRouterIdParameter() . '/send');
    }
}
