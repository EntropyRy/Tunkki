<?php

namespace App\Entity;

use App\Application\Sonata\MediaBundle\Entity\Media;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use function Symfony\Component\String\u;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EventRepository")
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE", region="event")
 */
class Event
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $Name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $Nimi;

    /**
     * @ORM\Column(type="datetime")
     */
    private $EventDate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $publishDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Application\Sonata\MediaBundle\Entity\Media" ,cascade={"persist"})
     */
    private $picture;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $css;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $Content = "Use these: <br>
            {{ timetable }} <br> {{ bios }} <br> {{ vj_bios }} <br> {{ rsvp }} <br> {{ links }}";

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $Sisallys = "Käytä näitä, vaikka monta kertaa: <br>
            {{ timetable }} <br> {{ bios }} <br> {{ vj_bios }} <br> {{ rsvp }} <br> {{ links }}";

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @ORM\Column(type="boolean")
     */
    private $published = false;

    /**
     * @ORM\Column(type="string", length=180)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=180, nullable=true)
     */
    private $epics;

    /**
     * @ORM\Column(type="boolean")
     */
    private $externalUrl = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private $sticky = false;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $picturePosition = 'banner';

    /**
     * @ORM\Column(type="boolean")
     */
    private $cancelled = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Application\Sonata\MediaBundle\Entity\Media")
     */
    private $attachment;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $links = [];

    /**
     * @ORM\OneToMany(targetEntity=EventArtistInfo::class, mappedBy="Event")
     * @ORM\OrderBy({"StartTime" = "ASC"})
     */
    private $eventArtistInfos;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $until;

    /**
     * @ORM\OneToMany(targetEntity=RSVP::class, mappedBy="event", orphanRemoval=true)
     */
    private $RSVPs;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $rsvpSystemEnabled = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(string $Name): self
    {
        $this->Name = $Name;

        return $this;
    }

    public function getNimi(): ?string
    {
        return $this->Nimi;
    }

    public function setNimi(string $Nimi): self
    {
        $this->Nimi = $Nimi;

        return $this;
    }

    public function getEventDate(): ?\DateTimeInterface
    {
        return $this->EventDate;
    }

    public function setEventDate(\DateTimeInterface $EventDate): self
    {
        $this->EventDate = $EventDate;

        return $this;
    }

    public function getPublishDate(): ?\DateTimeInterface
    {
        return $this->publishDate;
    }

    public function setPublishDate(\DateTimeInterface $publishDate): self
    {
        $this->publishDate = $publishDate;

        return $this;
    }

    public function getPicture(): ?Media
    {
        return $this->picture;
    }

    public function setPicture(?Media $picture): self
    {
        $this->picture = $picture;

        return $this;
    }

    public function getCss(): ?string
    {
        return $this->css;
    }

    public function setCss(?string $css): self
    {
        $this->css = $css;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->Content;
    }

    public function setContent(string $Content): self
    {
        $this->Content = $Content;

        return $this;
    }

    public function getSisallys(): ?string
    {
        return $this->Sisallys;
    }

    public function setSisallys(string $Sisallys): self
    {
        $this->Sisallys = $Sisallys;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }
    public function __toString()
    {
        return $this->getName() ? $this->getName() : 'Happening';
    }

    public function getPublished(): ?bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;

        return $this;
    }
    public function __construct()
    {
        $this->publishDate = new \DateTime();
        $this->css = "/* If you want to play with CSS these help you. First remove this and last line
header {
    display: none; 
}
footer {
    display: none !important;
}
body { 
    background: black;
}
.container {
    background: transparent;
    color: red;
}
.img-fluid {  

}
.img-right {  

}
*/";
		$this->eventArtistInfos = new ArrayCollection();
  $this->RSVPs = new ArrayCollection();
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEpics(): ?string
    {
        return $this->epics;
    }

    public function setEpics(?string $epics): self
    {
        $this->epics = $epics;

        return $this;
    }

    public function getExternalUrl(): ?bool
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(bool $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getSticky(): ?bool
    {
        return $this->sticky;
    }

    public function setSticky(bool $sticky): self
    {
        $this->sticky = $sticky;

        return $this;
    }

    public function getPicturePosition(): ?string
    {
        return $this->picturePosition;
    }

    public function setPicturePosition(string $picturePosition): self
    {
        $this->picturePosition = $picturePosition;

        return $this;
    }

    public function getCancelled(): ?bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): self
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    public function getAttachment(): ?Media
    {
        return $this->attachment;
    }

    public function setAttachment(?Media $attachment): self
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function setLinks(?array $links): self
    {
        $this->links = $links;

        return $this;
    }
    public function getAbstract($lang)
    {
        if ($lang=='fi'){
            $abstract = $this->removeTwigTags($this->Sisallys);

        } else {
            $abstract = $this->removeTwigTags($this->Content);
        }
        return u(html_entity_decode(strip_tags($abstract)))->truncate(150,'..');
    }
    protected function removeTwigTags($message)
    {
        $abstract = str_replace("{{ bios }}", "",$message);
        $abstract = str_replace("{{ timetable }}", "",$abstract);
        $abstract = str_replace("{{ vj_bios }}", "",$abstract);
        $abstract = str_replace("{{ rsvp }}", "",$abstract);
        $abstract = str_replace("{{ links }}", "",$abstract);
        return $abstract;
    }
    public function getNameByLang($lang)
    {
        if($lang=='fi'){
            return $this->Nimi;
        } else {
            return $this->Name;
        }
    }

    /**
     * @return Collection|EventArtistInfo[]
     */
    public function getEventArtistInfos(): Collection
    {
        return $this->eventArtistInfos;
    }

    public function addEventArtistInfo(EventArtistInfo $eventArtistInfo): self
    {
        if (!$this->eventArtistInfos->contains($eventArtistInfo)) {
            $this->eventArtistInfos[] = $eventArtistInfo;
            $eventArtistInfo->setEvent($this);
        }

        return $this;
    }

    public function removeEventArtistInfo(EventArtistInfo $eventArtistInfo): self
    {
        if ($this->eventArtistInfos->contains($eventArtistInfo)) {
            $this->eventArtistInfos->removeElement($eventArtistInfo);
            // set the owning side to null (unless already changed)
            if ($eventArtistInfo->getEvent() === $this) {
                $eventArtistInfo->setEvent(null);
            }
        }
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUntil(): ?\DateTimeInterface
    {
        return $this->until;
    }

    public function setUntil(?\DateTimeInterface $until): self
    {
        $this->until = $until;

        return $this;
    }

    /**
     * @return Collection|RSVP[]
     */
    public function getRSVPs(): Collection
    {
        return $this->RSVPs;
    }

    public function addRSVP(RSVP $rSVP): self
    {
        if (!$this->RSVPs->contains($rSVP)) {
            $this->RSVPs[] = $rSVP;
            $rSVP->setEvent($this);
        }

        return $this;
    }

    public function removeRSVP(RSVP $rSVP): self
    {
        if ($this->RSVPs->removeElement($rSVP)) {
            // set the owning side to null (unless already changed)
            if ($rSVP->getEvent() === $this) {
                $rSVP->setEvent(null);
            }
        }

        return $this;
    }

    public function getRsvpSystemEnabled(): ?bool
    {
        return $this->rsvpSystemEnabled;
    }

    public function setRsvpSystemEnabled(?bool $rsvpSystemEnabled): self
    {
        $this->rsvpSystemEnabled = $rsvpSystemEnabled;

        return $this;
    }
}
