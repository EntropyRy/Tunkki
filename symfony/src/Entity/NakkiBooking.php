<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NakkiBookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NakkiBookingRepository::class)]
class NakkiBooking implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Nakki::class, inversedBy: 'nakkiBookings')]
    #[ORM\JoinColumn(nullable: false)]
    private Nakki $nakki;

    #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'nakkiBookings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Member $member = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endAt;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'nakkiBookings')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[\Override]
    public function __toString(): string
    {
        if ($this->getEvent()->isNakkiRequiredForTicketReservation()) {
            return $this->event.': '.$this->nakki;
        }
        $aika = $this->getStartAt()->format('H:i');

        return $this->event.': '.$this->nakki.' at '.$aika;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNakki(): Nakki
    {
        return $this->nakki;
    }

    public function setNakki(Nakki $nakki): self
    {
        $this->nakki = $nakki;

        return $this;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getMemberEmail(): ?string
    {
        return $this->member instanceof Member ? $this->member->getEmail() : null;
    }

    public function memberHasEventTicket(): bool
    {
        return $this->member && $this->event->memberHasTicket($this->member);
    }
}
