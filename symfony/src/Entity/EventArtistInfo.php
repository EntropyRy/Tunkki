<?php

namespace App\Entity;

use App\Repository\EventArtistInfoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EventArtistInfoRepository::class)
 */
class EventArtistInfo
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $SetLength;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $StartTime;

    /**
     * @ORM\ManyToOne(targetEntity=Event::class, inversedBy="eventArtistInfos")
     */
    private $Event;

    /**
     * @ORM\ManyToOne(targetEntity=Artist::class, inversedBy="eventArtistInfos")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $Artist;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $WishForPlayTime;

    /**
     * @ORM\ManyToOne(targetEntity=Artist::class, cascade={"persist"})
     */
    private $artistClone;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $freeWord;

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
    public function __toString()
    {
        return $this->Artist;
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
}
