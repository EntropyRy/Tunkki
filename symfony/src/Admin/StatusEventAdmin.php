<?php

namespace App\Admin;

use App\Entity\StatusEvent;
use App\Service\MattermostNotifierService;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @extends AbstractAdmin<StatusEvent>
 */
class StatusEventAdmin extends AbstractAdmin
{
    #[\Override]
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('item')
            ->add('booking')
            ->add('description')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('creator');
    }

    #[\Override]
    protected function configureListFields(ListMapper $listMapper): void
    {
        if (!$this->isChild()) {
            $listMapper->add('item');
            $listMapper->add('booking');
        }
        $listMapper
            ->add('description')
            ->add('creator')
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                ],
            ]);
    }

    #[\Override]
    protected function configureFormFields(FormMapper $formMapper): void
    {
        if (!$this->isChild()) {
            $formMapper
                ->with('Item', ['class' => 'col-md-12'])
                ->add('item')
                ->end();
            $formMapper
                ->with('Booking', ['class' => 'col-md-12'])
                ->add('booking')
                ->end();
        }
        if (null != $this->getSubject()->getItem()) {
            $events = array_reverse($this->getSubject()->getItem()->getFixingHistory()->slice(0, 5));
            $help = '';
            foreach ($events as $event) {
                $help .= '['.$event->getCreatedAt()->format('d.m.y H:i').'] '.$event->getCreator().': '.$event->getDescription().'<br>';
            }
            $formMapper
                ->with('Status', ['class' => 'col-md-4'])
                ->add('item.cannotBeRented', CheckboxType::class, ['required' => false])
                ->add('item.needsFixing', CheckboxType::class, ['required' => false])
                ->add('item.forSale', CheckboxType::class, ['required' => false])
                ->add('item.toSpareParts', CheckboxType::class, ['required' => false])
                ->end()
                ->with('Message', ['class' => 'col-md-8'])
                ->add('description', TextareaType::class, [
                    'required' => true,
                    'help' => $help,
                    'help_html' => true,
                ])
                ->end();
        }
        if (null != $this->getSubject()->getBooking()) {
            $formMapper
                ->with('Status', ['class' => 'col-md-4'])
                ->add('booking.cancelled', CheckboxType::class, ['required' => false])
                ->add('booking.renterConsent', CheckboxType::class, ['required' => false, 'disabled' => true])
                ->add('booking.itemsReturned', CheckboxType::class, ['required' => false])
                ->add('booking.invoiceSent', CheckboxType::class, ['required' => false])
                ->add('booking.paid', CheckboxType::class, [
                    'required' => false,
                    'help' => 'please make sure booking handler has been selected',
                ])
                ->add('booking.givenAwayBy', null, ['disabled' => true])
                ->add('booking.receivedBy', null, ['disabled' => true])
                ->end()
                ->with('Message', ['class' => 'col-md-8'])
                ->add('description', TextareaType::class, [
                    'required' => true,
                    'help' => 'Describe in more detail. Will be visible for others in Mattermost.',
                ])
                ->end();
        }
        if (!$this->isChild()) {
            $formMapper
                ->with('Meta')
                ->add('creator', null, ['disabled' => true])
                ->add('createdAt', DateTimePickerType::class, ['disabled' => true])
                ->add('modifier', null, ['disabled' => true])
                ->add('updatedAt', DateTimePickerType::class, ['disabled' => true])
                ->end();
        }
    }

    #[\Override]
    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('item')
            ->add('booking')
            ->add('description')
            ->add('creator')
            ->add('createdAt')
            ->add('modifier')
            ->add('updatedAt');
    }

    #[\Override]
    public function prePersist($Event): void
    {
        $user = $this->ts->getToken()->getUser();
        $Event->setCreator($user);
        $Event->setModifier($user);
    }

    #[\Override]
    public function postPersist($Event): void
    {
        $user = $Event->getCreator();
        $text = $this->getMMtext($Event, $user);
        $this->mm->sendToMattermost($text, 'vuokraus');
    }

    #[\Override]
    public function preUpdate($Event): void
    {
        $user = $this->ts->getToken()->getUser();
        $Event->setModifier($user);
    }

    #[\Override]
    public function postUpdate($Event): void
    {
        $user = $Event->getModifier();
        $text = $this->getMMtext($Event, $user);
        $this->mm->sendToMattermost($text, 'vuokraus');
    }

    private function getMMtext($Event, string $user): string
    {
        $url = $this->generateUrl('show', ['id' => $Event->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $fix = null;
        $rent = null;
        if (!empty($Event->getItem())) {
            $thing = $Event->getItem();
            $fix = $thing->getNeedsFixing();
            $rent = $thing->getCannotBeRented();
            $text = 'EVENT: ['.$thing->getName().']('.$url.') ';
            if (true === $fix) {
                $text .= '**_NEEDS FIXING_** ';
            } elseif (false === $fix) {
                $text .= '**_FIXED_** ';
            }
            if (true === $rent) {
                $text .= 'cannot be rented ';
            } elseif (false === $fix) {
                $text .= 'can be rented ';
            }
        } else {
            $thing = $Event->getBooking();
            $text = 'EVENT: ['.$thing->getName().']('.$url.') ';
        }
        if ($Event->getDescription()) {
            $text .= 'with comment: '.$Event->getDescription();
        }

        return $text.(' by '.$user);
    }

    public function __construct(protected MattermostNotifierService $mm, protected TokenStorageInterface $ts)
    {
    }
}
