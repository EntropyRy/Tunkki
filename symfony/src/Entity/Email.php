<?php

namespace App\Entity;

use App\Repository\EmailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Email implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    private ?string $body = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $purpose = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $addLoginLinksToFooter = true;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'emails')]
    private ?Event $event = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $SentAt = null;

    #[ORM\ManyToOne]
    private ?Member $sentBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

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

    public function getAddLoginLinksToFooter(): ?bool
    {
        return $this->addLoginLinksToFooter;
    }

    public function setAddLoginLinksToFooter(?bool $addLoginLinksToFooter): self
    {
        $this->addLoginLinksToFooter = $addLoginLinksToFooter;

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->purpose ?: 'Email for '.$this->event;
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

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function setReplyTo(?string $replyTo): self
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->SentAt;
    }

    public function setSentAt(?\DateTimeImmutable $SentAt): self
    {
        $this->SentAt = $SentAt;

        return $this;
    }

    public function getSentBy(): ?Member
    {
        return $this->sentBy;
    }

    public function setSentBy(?Member $sentBy): static
    {
        $this->sentBy = $sentBy;

        return $this;
    }
}
