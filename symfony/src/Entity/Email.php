<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailPurpose;
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
    private string $subject = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, enumType: EmailPurpose::class)]
    private ?EmailPurpose $purpose = null;

    /**
     * @var array<EmailPurpose|string> Doctrine JSON column returns strings, converted to enums in getter
     */
    #[ORM\Column(type: Types::JSON)]
    private array $recipientGroups = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

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

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

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

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getPurpose(): ?EmailPurpose
    {
        return $this->purpose;
    }

    public function setPurpose(?EmailPurpose $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    /**
     * @return array<EmailPurpose>
     */
    public function getRecipientGroups(): array
    {
        $recipientGroups = $this->recipientGroups ?? [];

        // Convert strings to enum instances if needed (from JSON deserialization)
        return array_map(
            static fn (EmailPurpose|string $value) => $value instanceof EmailPurpose ? $value : EmailPurpose::from($value),
            $recipientGroups
        );
    }

    /**
     * @param array<EmailPurpose|string> $recipientGroups
     */
    public function setRecipientGroups(array $recipientGroups): self
    {
        // Store as string values for JSON serialization
        $this->recipientGroups = array_map(
            static fn (EmailPurpose|string $value) => $value instanceof EmailPurpose ? $value->value : $value,
            $recipientGroups
        );

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
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
        return $this->purpose?->label() ?? 'Email for '.$this->event;
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
