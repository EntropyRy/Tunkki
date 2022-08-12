<?php

namespace App\Entity;

use App\Repository\NakkiBookingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NakkiBookingRepository::class)
 */
class NakkiBooking
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Nakki::class, inversedBy="nakkiBookings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $nakki;

    /**
     * @ORM\ManyToOne(targetEntity=Member::class, inversedBy="nakkiBookings")
     * @ORM\JoinColumn(nullable=true)
     */
    private $member;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $startAt;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $endAt;

    /**
     * @ORM\ManyToOne(targetEntity=Event::class, inversedBy="nakkiBookings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $event;

    public function __toString()
    {
        return $this->event.': '.$this->nakki.' at '. $this->startAt->format('H:i');
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNakki(): ?Nakki
    {
        return $this->nakki;
    }

    public function setNakki(?Nakki $nakki): self
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

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getMemberEmail(): ?string
    {
        return $this->member ? $this->member->getEmail() : null;
    }
}
