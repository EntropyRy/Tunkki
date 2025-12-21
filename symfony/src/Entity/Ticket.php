<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket implements \Stringable
{
    public const STATUSES = ['available', 'reserved', 'paid', 'paid_with_bus'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Member $owner = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $price;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $referenceNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Choice(choices: self::STATUSES)]
    private string $status = 'available';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?bool $given = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->referenceNumber;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOwner(): ?Member
    {
        return $this->owner;
    }

    public function setOwner(?Member $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getReferenceNumber(): ?int
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(int $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getOwnerEmail(): ?string
    {
        return $this->owner instanceof Member ? $this->owner->getEmail() : null;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function ticketHolderHasNakki(): ?NakkiBooking
    {
        $member = $this->getOwner();
        if ($member instanceof Member) {
            // find member Nakki only if it is mandatory
            $nakkikone = $this->event->getNakkikone();

            return $nakkikone?->ticketHolderHasNakki($member);
        }

        return null;
    }

    public function isGiven(): ?bool
    {
        return $this->given;
    }

    public function setGiven(?bool $given): static
    {
        $this->given = $given;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(?string $stripeProductId): static
    {
        $this->stripeProductId = $stripeProductId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
