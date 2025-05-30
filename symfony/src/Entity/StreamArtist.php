<?php

namespace App\Entity;

use App\Repository\StreamArtistRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StreamArtistRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StreamArtist implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Artist $artist = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $stoppedAt = null;

    #[ORM\ManyToOne(inversedBy: 'artists')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stream $stream = null;

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->artist->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getArtist(): ?Artist
    {
        return $this->artist;
    }

    public function setArtist(?Artist $artist): static
    {
        $this->artist = $artist;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getStoppedAt(): ?\DateTimeImmutable
    {
        return $this->stoppedAt;
    }

    public function setStoppedAt(?\DateTimeImmutable $stoppedAt): static
    {
        $this->stoppedAt = $stoppedAt;

        return $this;
    }

    public function getStream(): ?Stream
    {
        return $this->stream;
    }

    public function setStream(?Stream $stream): static
    {
        $this->stream = $stream;

        return $this;
    }
}
