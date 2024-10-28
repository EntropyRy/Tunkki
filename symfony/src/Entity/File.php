<?php

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia as Media;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'File')]
#[ORM\Entity]
class File implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'tiedostoinfo', type: \Doctrine\DBAL\Types\Types::STRING, length: 190, nullable: true)]
    private ?string $fileinfo = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Item::class, inversedBy: 'files')]
    private ?\App\Entity\Item $product = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    private ?Media $file = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setFileinfo($fileinfo): self
    {
        $this->fileinfo = $fileinfo;

        return $this;
    }

    public function getFileinfo(): ?string
    {
        return $this->fileinfo;
    }

    public function setProduct(\App\Entity\Item $product = null): self
    {
        $this->product = $product;

        return $this;
    }

    public function getProduct(): ?Item
    {
        return $this->product;
    }

    public function setFile(Media $file = null): self
    {
        $this->file = $file;

        return $this;
    }

    public function getFile(): ?Media
    {
        return $this->file;
    }

    public function getDownloadLink(): string
    {
        if (is_object($this->getFile())) {
            if (is_string($this->getFileinfo())) {
                return '<a href="/media/download/' . $this->getFile()->getId() . '">' . $this->getFileinfo() . '</a>';
            } else {
                return '<a href="/media/download/' . $this->getFile()->getId() . '">Download</a>';
            }
        } else {
            return 'X';
        }
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->fileinfo ?: '';
    }
}
