<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventArtistInfoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventArtistInfoRepository::class)]
class EventArtistInfo implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $SetLength = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $StartTime = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'eventArtistInfos')]
    private ?Event $Event = null;

    #[ORM\ManyToOne(targetEntity: Artist::class, cascade: ['persist'], inversedBy: 'eventArtistInfos'),]
    private ?Artist $Artist = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $WishForPlayTime = null;

    #[ORM\ManyToOne(targetEntity: Artist::class, cascade: ['persist'])]
    private ?Artist $artistClone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $freeWord = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stage = null;

    #[ORM\Column(nullable: true)]
    private ?bool $agreeOnRecording = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSetLength(): ?string
    {
        return $this->SetLength;
    }

    public function setSetLength(?string $SetLength): self
    {
        $this->SetLength = $SetLength;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->StartTime;
    }

    public function setStartTime(?\DateTimeInterface $StartTime): self
    {
        $this->StartTime = $StartTime instanceof \DateTimeImmutable
            ? $StartTime
            : ($StartTime instanceof \DateTime ? \DateTimeImmutable::createFromInterface($StartTime) : null);

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->Event;
    }

    public function setEvent(?Event $Event): self
    {
        $this->Event = $Event;

        return $this;
    }

    public function getArtist(): ?Artist
    {
        return $this->Artist;
    }

    public function setArtist(?Artist $Artist): self
    {
        $this->Artist = $Artist;
        $Artist->addEventArtistInfo($this);

        return $this;
    }

    public function removeArtist(): self
    {
        $this->Artist = null;

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) ($this->Artist ?: 'n/a');
    }

    public function getWishForPlayTime(): ?string
    {
        return $this->WishForPlayTime;
    }

    public function setWishForPlayTime(?string $WishForPlayTime): self
    {
        $this->WishForPlayTime = $WishForPlayTime;

        return $this;
    }

    public function getArtistClone(): ?Artist
    {
        return $this->artistClone;
    }

    public function setArtistClone(?Artist $artistClone): self
    {
        $this->artistClone = $artistClone;

        return $this;
    }

    public function getFreeWord(): ?string
    {
        return $this->freeWord;
    }

    public function setFreeWord(?string $freeWord): self
    {
        $this->freeWord = $freeWord;

        return $this;
    }

    public function getStage(): ?string
    {
        return $this->stage;
    }

    public function setStage(?string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public function timediff(?\DateTimeInterface $date): ?int
    {
        if ($date instanceof \DateTimeInterface) {
            return (int) $date->diff($this->StartTime)->format('%r%h');
        }

        return null;
    }

    public function isAgreeOnRecording(): ?bool
    {
        return $this->agreeOnRecording;
    }

    public function setAgreeOnRecording(?bool $agreeOnRecording): static
    {
        $this->agreeOnRecording = $agreeOnRecording;

        return $this;
    }
}
