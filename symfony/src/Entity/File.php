<?php

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia as Media;
use Doctrine\ORM\Mapping as ORM;

/**
 * File
 */
#[ORM\Table(name: 'File')]
#[ORM\Entity]
class File implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'tiedostoinfo', type: 'string', length: 190, nullable: true)]
    private ?string $fileinfo = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Item::class, inversedBy: 'files')]
    private ?\App\Entity\Item $product = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    private ?\App\Entity\Sonata\SonataMediaMedia $file = null;


    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set fileinfo
     *
     * @param string $fileinfo
     *
     * @return File
     */
    public function setFileinfo($fileinfo): File
    {
        $this->fileinfo = $fileinfo;

        return $this;
    }

    /**
     * Get fileinfo
     *
     * @return string
     */
    public function getFileinfo(): ?string
    {
        return $this->fileinfo;
    }

    /**
     * Set product
     *
     * @return File
     */
    public function setProduct(\App\Entity\Item $product = null): File
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product
     *
     * @return \App\Entity\Item
     */
    public function getProduct(): ?Item
    {
        return $this->product;
    }

    /**
     * Set file
     *
     * @return File
     */
    public function setFile(Media $file = null): File
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file
     *
     * @return Media
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Get Download
     */
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

    public function __toString(): string
    {
        return $this->fileinfo ?: '';
    }
}
