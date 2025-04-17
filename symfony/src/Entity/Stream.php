<?php

namespace App\Entity;

use App\Repository\StreamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StreamRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Stream implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?int $listeners = 0;

    #[ORM\Column]
    private ?bool $online = null;

    /**
     * @var Collection<int, StreamArtist>
     */
    #[ORM\OneToMany(targetEntity: StreamArtist::class, mappedBy: 'stream', orphanRemoval: true)]
    private Collection $artists;

    #[ORM\Column(length: 190)]
    private ?string $filename = null;

    public function __construct()
    {
        $this->artists = new ArrayCollection();
    }

    public function __toString(): string
    {
        return 'Stream: '.$this->getCreatedAt()->format('d.m.Y H:i:s');
    }
    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getListeners(): ?int
    {
        return $this->listeners;
    }

    public function setListeners(int $listeners): static
    {
        $this->listeners = $listeners;

        return $this;
    }

    public function isOnline(): ?bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): static
    {
        $this->online = $online;

        return $this;
    }

    /**
     * @return Collection<int, StreamArtist>
     */
    public function getArtists(): Collection
    {
        return $this->artists;
    }

    public function addArtist(StreamArtist $artist): static
    {
        if (!$this->artists->contains($artist)) {
            $this->artists->add($artist);
            $artist->setStream($this);
        }

        return $this;
    }

    public function removeArtist(StreamArtist $artist): static
    {
        if ($this->artists->removeElement($artist)) {
            // set the owning side to null (unless already changed)
            if ($artist->getStream() === $this) {
                $artist->setStream(null);
            }
        }

        return $this;
    }

    public function getArtistsOnline(): Collection
    {
        return $this->artists->filter(fn (StreamArtist $artist) => $artist->getStoppedAt() === null);
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getMp3Filename(): string
    {
        return $this->filename . '.mp3';
    }
    public function getOpusFilename(): string
    {
        return $this->filename . '.opus';
    }
    public function getFlacFilename(): string
    {
        return $this->filename . '_unprocessed.flac';
    }
}
