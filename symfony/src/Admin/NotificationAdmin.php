<?php

declare(strict_types=1);

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
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
            ->add('message')
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
            ->add('message', null, [
                'help_html' => true,
                'help' => 'Message will always inclue url to the event after this text. Use these for formatting: <br>*bold \*text*<br>
_italic \*text_<br>
__underline__<br>
~strikethrough~<br>
||spoiler||<br>
*bold _italic bold ~italic bold strikethrough ||italic bold strikethrough spoiler||~ __underline italic bold___ bold*<br>
[inline URL](http://www.example.com/)<br>
[inline mention of a user](tg://user?id=123456789)'
            ])
            ->add('options', ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'add_event_button' => 'add_event_button',
                    'add_nakkikone_button' => 'add_nakkikone_button'
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
