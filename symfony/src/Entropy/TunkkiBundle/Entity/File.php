<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Files
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class File
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="tiedostoinfo", type="string", length=255, nullable=true)
     */
    private $fileinfo;

    /**
     * @ORM\ManyToOne(targetEntity="Entropy\TunkkiBundle\Entity\Item", inversedBy="files")
     *
     */
    private $product;

    /**
     *
     * @ORM\ManyToOne(targetEntity="Application\Sonata\MediaBundle\Entity\Media",  cascade={"persist"})
     */
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
     * @param \Entropy\TunkkiBundle\Entity\Item $product
     *
     * @return Files
     */
    public function setProduct(\Entropy\TunkkiBundle\Entity\Item $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product
     *
     * @return \Entropy\TunkkiBundle\Entity\Item
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set file
     *
     * @param \Application\Sonata\MediaBundle\Entity\Media $file
     *
     * @return Files
     */
    public function setFile(\Application\Sonata\MediaBundle\Entity\Media $file = null)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file
     *
     * @return \Application\Sonata\MediaBundle\Entity\Media
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
    public function getDownload()
    {
        if (is_object($this->getFile())){
            return '<a href="/media/download/'.$this->getFile()->getId().'">Download</a>';
        } 
        else { return 'X'; }
    }

    public function __toString()
    {
        return '' ? $this->fileinfo : $this->fileinfo;
    }
}
