<?php

namespace App\Entity;

use App\Repository\EventArtistInfoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventArtistInfoRepository::class)]
class EventArtistInfo implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $SetLength = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $StartTime = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'eventArtistInfos')]
    private ?\App\Entity\Event $Event = null;

    #[ORM\ManyToOne(targetEntity: Artist::class, inversedBy: 'eventArtistInfos', cascade: ['persist'])]
    private ?\App\Entity\Artist $Artist = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $WishForPlayTime = null;

    #[ORM\ManyToOne(targetEntity: Artist::class, cascade: ['persist'])]
    private ?\App\Entity\Artist $artistClone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $freeWord = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $stage = null;

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

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->StartTime;
    }

    public function setStartTime(?\DateTimeInterface $StartTime): self
    {
        $this->StartTime = $StartTime;

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
        if ($date) {
            return (int)$date->diff($this->StartTime)->format('%r%h');
        }
        return null;
    }
    public function getArtistDataHasUpdate(\DateTimeInterface $eventDate): bool
    {
        if ($eventDate < (new \DateTime('now'))->modify('-1 day')) {
            return false;
        }
        if ($this->getArtist()) {
            return ($this->getArtistClone()->getUpdatedAt()->format('U') >= $this->getArtist()->getUpdatedAt()->format('U')) ? false : true;
        }
        return false;
    }
    public function getArtistName(): string
    {
        return $this->getArtist() ? $this->getArtist()->getName() : $this->getArtistClone()->getName();
    }
}
