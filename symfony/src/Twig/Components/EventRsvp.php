<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Domain\EventTemporalStateService;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\RSVP;
use App\Form\RSVPType;
use App\Repository\MemberRepository;
use App\Repository\RSVPRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class EventRsvp
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp(updateFromParent: true)]
    public Event $event;

    #[LiveProp(updateFromParent: true)]
    public ?Member $member = null;

    #[LiveProp(writable: true)]
    public bool $formOpen = false;

    #[LiveProp(writable: true)]
    public ?string $notice = null;

    #[LiveProp(writable: true)]
    public ?string $error = null;

    public function __construct(
        private readonly RSVPRepository $rsvpRepository,
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly EventTemporalStateService $eventTemporalState,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function hasRsvpd(): bool
    {
        if (!$this->member instanceof Member) {
            return false;
        }

        return $this->rsvpRepository->existsForMemberAndEvent(
            $this->member,
            $this->event,
        );
    }

    public function isVisible(): bool
    {
        return (bool) $this->event->getRsvpSystemEnabled()
            && !$this->eventTemporalState->isInPast($this->event);
    }

    #[LiveAction]
    public function openForm(): void
    {
        if ($this->member instanceof Member) {
            return;
        }

        $this->formOpen = true;
        $this->error = null;
        $this->notice = null;
    }

    #[LiveAction]
    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->error = null;
        $this->notice = null;
        $this->resetForm(true);
    }

    #[LiveAction]
    public function saveAnonymous(): void
    {
        $this->error = null;
        $this->notice = null;

        $this->submitForm();

        /** @var RSVP $rsvp */
        $rsvp = $this->getForm()->getData();

        $exists = $this->memberRepository->findByEmailOrName(
            $rsvp->getEmail() ?? '',
            $rsvp->getFirstName() ?? '',
            $rsvp->getLastName() ?? '',
        );
        if ($exists) {
            $this->error = $this->translator->trans('rsvp.email_in_use');

            return;
        }

        $rsvp->setEvent($this->event);

        $this->entityManager->persist($rsvp);
        $this->entityManager->flush();

        $this->notice = $this->translator->trans('rsvp.rsvpd_successfully');
        $this->formOpen = false;
        $this->resetForm();
    }

    #[LiveAction]
    public function rsvpAsMember(): void
    {
        $this->error = null;
        $this->notice = null;
        if (!$this->member instanceof Member) {
            $this->error = $this->translator->trans('rsvp.no_user');

            return;
        }
        if ($this->rsvpRepository->existsForMemberAndEvent($this->member, $this->event)) {
            $this->error = $this->translator->trans('rsvp.already_rsvpd');

            return;
        }
        $rsvp = new RSVP();
        $rsvp->setEvent($this->event);
        $rsvp->setMember($this->member);
        $this->entityManager->persist($rsvp);
        $this->entityManager->flush();
        $this->notice = $this->translator->trans('rsvp.rsvpd_successfully');
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(RSVPType::class, new RSVP());
    }
}
