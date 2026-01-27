<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Contract implements \Stringable
{
    /**
     * Canonical (English) purpose slugs stored in DB.
     *
     * @var array<string, string>
     */
    public const array PURPOSES = [
        'rent' => 'rental-contract',
        'privacy_notice' => 'privacy-notice',
        'rsvp_privacy_notice' => 'rsvp-privacy-notice',
    ];

    /**
     * Localized Finnish slugs for public routes.
     *
     * @var array<string, string>
     */
    public const array SLUGS_FI = [
        'rent' => 'vuokrasopimus',
        'privacy_notice' => 'rekisteriseloste',
        'rsvp_privacy_notice' => 'rsvp-rekisteriseloste',
    ];
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Primary (Finnish) content is required. Empty string sentinel until explicitly set.
     */
    #[ORM\Column(type: 'text')]
    private string $ContentFi = '';

    /**
     * Stored as immutable timestamp. Migration required if DB platform differentiates.
     * Non-null invariant (set in constructor + lifecycle).
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $purpose = '';

    /**
     * Stored as immutable timestamp. Set on persist only. Non-null invariant.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ContentEn = null;

    /**
     * Domain decision: validFrom represents a planned activation instant; immutable is appropriate.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

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
        // Preserve constructor initialization but normalize both to current instant at first persist.
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getContentFi(): string
    {
        return $this->ContentFi;
    }

    public function setContentFi(string $ContentFi): self
    {
        $this->ContentFi = $ContentFi;

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

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Setter retained only if needed for fixtures/manual adjustments.
     * Prefer not to modify createdAt after persistence.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        return '' !== $this->purpose ? $this->purpose : 'purpose';
    }

    public function getContentEn(): ?string
    {
        return $this->ContentEn;
    }

    public function setContentEn(?string $ContentEn): self
    {
        $this->ContentEn = $ContentEn;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }
}
