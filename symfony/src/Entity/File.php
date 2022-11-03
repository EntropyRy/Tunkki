<?php

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia as Media;
use Doctrine\ORM\Mapping as ORM;

/**
 * Files
 */
#[ORM\Table(name: 'File')]
#[ORM\Entity]
class File implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private readonly int $id;

    #[ORM\Column(name: 'tiedostoinfo', type: 'string', length: 190, nullable: true)]
    private ?string $fileinfo = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Item::class, inversedBy: 'files')]
    private $product;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    private $file;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set fileinfo
     *
     * @param string $fileinfo
     *
     * @return Files
     */
    public function setFileinfo($fileinfo)
    {
        $this->fileinfo = $fileinfo;

        return $this;
    }

    /**
     * Get fileinfo
     *
     * @return string
     */
    public function getFileinfo()
    {
        return $this->fileinfo;
    }

    /**
     * Set product
     *
     *
     * @return Files
     */
    public function setProduct(\App\Entity\Item $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product
     *
     * @return \App\Entity\Item
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set file
     *
     *
     * @return Files
     */
    public function setFile(Media $file = null)
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
     *
     * @return string
     */
    public function getDownloadLink()
    {
        if (is_object($this->getFile())) {
            if (is_string($this->getFileinfo())) {
                return '<a href="/media/download/'.$this->getFile()->getId().'">'.$this->getFileinfo().'</a>';
            } else {
                return '<a href="/media/download/'.$this->getFile()->getId().'">Download</a>';
            }
        } else {
            return 'X';
        }
    }

    public function __toString(): string
    {
        return $this->fileinfo ?: '' ;
    }
}
