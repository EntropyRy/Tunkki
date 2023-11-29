<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\EmailRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Email implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    private ?string $body = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $purpose = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $addLoginLinksToFooter = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'emails')]
    private ?\App\Entity\Event $event = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $SentAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
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
    public function __toString(): string
    {
        return (string) ($this->purpose ?: 'Email for ' . $this->event);
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
}
