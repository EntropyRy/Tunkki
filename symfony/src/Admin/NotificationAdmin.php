<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Notification;
use App\Form\MarkdownEditorType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractAdmin<Notification>
 */
final class NotificationAdmin extends AbstractAdmin
{
    #[\Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'notification';
    }

    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('event')
            ->add('updatedAt')
            ->add('sentAt');
    }

    #[\Override]
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('message', 'html', [
                'safe' => true,
            ])
            ->add('updatedAt')
            ->add('sentAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'send' => [
                        'template' => 'admin/event/button_announce_telegram.html.twig',
                    ],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('locale', ChoiceType::class, [
                'choices' => [
                    'Finnish' => 'fi',
                    'English' => 'en',
                ],
                'help' => 'Defines links language',
            ])
            ->add('message', MarkdownEditorType::class, [
                'format' => 'telegram',
                'required' => true,
                'help' => 'Telegram supports: bold, italic, strikethrough, links. Lists and headings are not supported.',
            ])
            ->add('options', ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choices' => [
                    'Add event picture to the message header (can be added only on first send)' => 'add_event_picture',
                    'Preview link That is in the content (only if there is no header picture)' => 'add_preview_link',
                    'Send Notification Sound to everybody in the info channel (only works on first send)' => 'send_notification',
                    'Buttons: put each button on its own row' => 'buttons_one_per_row',
                    'Add Event Button' => 'add_event_button',
                    'Add Nakkikone Button' => 'add_nakkikone_button',
                    'Add Buy Ticket Button' => 'add_shop_button',
                    'Add Venue: Inserts map to the event venue defined in Event Location in Event-tab. If used it is the only info to send. Sent message cannot be edited. New message has to be sent' => 'add_venue',
                ],
            ]);
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('message')
            ->add('event')
            ->add('updatedAt')
            ->add('sentAt')
            ->add('messageId');
    }

    #[\Override]
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('show');
        $collection->remove('delete');
        $collection->add('send', $this->getRouterIdParameter().'/send');
    }
}
