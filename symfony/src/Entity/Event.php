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

    /**
     * @ORM\OneToMany(targetEntity=Nakki::class, mappedBy="event", orphanRemoval=true)
     */
    private $nakkis;

    /**
     * @ORM\OneToMany(targetEntity=NakkiBooking::class, mappedBy="event", orphanRemoval=true)
     */
    private $nakkiBookings;

    /**
     * @ORM\Column(type="boolean")
     */
    private $NakkikoneEnabled = false;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $nakkiInfoFi = 
        '
        <h5>Yleisinfo</h5>
        <p>Valitse vähintään 2 tunnin Nakkia sekä purku tai roudaus</p>
        <h6>Saat ilmaisen sisäänpääsyn</h6>
        ';

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $nakkiInfoEn = 
        '
        <h5>General information</h5>
        <p>Choose at least two Nakkis that are 1 hour length and build up or take down</p>
        <h6>You\'ll get free entry to the party</h6>
        ';

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $includeSaferSpaceGuidelines;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $RSVPEmailSubject;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $RSVPEmailBody;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $headerTheme = 'light';

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $streamPlayerUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $imgFilterColor;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $imgFilterBlendMode;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $artistSignUpEnabled = false;

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
body {
    background-image: url(/images/bg_stripe_black.png); 
}
.container {
    background: #220101;
    color: red;
}
.img-filter {
    /* possible animations: morph and transparent_morph, still color with background */
    /* animation: morph 8s infinite; */
    background: #00FFFF;
}
.img-filter img {
    mix-blend-mode: difference;
}
*/";
		$this->eventArtistInfos = new ArrayCollection();
        $this->RSVPs = new ArrayCollection();
        $this->nakkis = new ArrayCollection();
        $this->nakkiBookings = new ArrayCollection();
    }
    public function getNowTest(): ?string
    {
        $now = new \DateTime();
        if($this->until){
            if ($now >= $this->EventDate && $now <= $this->until ){
                return 'now';
            } elseif ($now > $this->until) {
                return 'after';
            } elseif ($now < $this->EventDate) {
                return 'before';
            }
        } else {
            if ( $now < $this->EventDate ){
                return 'before';
            } else {
                return 'after';
            }
        }
        return false;
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
        $abstract = str_replace("{{ streamplayer }}", "",$abstract);
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

    /**
     * @return Collection|Nakki[]
     */
    public function getNakkis(): Collection
    {
        return $this->nakkis;
    }

    public function addNakki(Nakki $nakki): self
    {
        if (!$this->nakkis->contains($nakki)) {
            $this->nakkis[] = $nakki;
            $nakki->setEvent($this);
        }

        return $this;
    }

    public function removeNakki(Nakki $nakki): self
    {
        if ($this->nakkis->removeElement($nakki)) {
            // set the owning side to null (unless already changed)
            if ($nakki->getEvent() === $this) {
                $nakki->setEvent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|NakkiBooking[]
     */
    public function getNakkiBookings(): Collection
    {
        return $this->nakkiBookings;
    }

    public function addNakkiBooking(NakkiBooking $nakkiBooking): self
    {
        if (!$this->nakkiBookings->contains($nakkiBooking)) {
            $this->nakkiBookings[] = $nakkiBooking;
            $nakkiBooking->setEvent($this);
        }

        return $this;
    }

    public function removeNakkiBooking(NakkiBooking $nakkiBooking): self
    {
        if ($this->nakkiBookings->removeElement($nakkiBooking)) {
            // set the owning side to null (unless already changed)
            if ($nakkiBooking->getEvent() === $this) {
                $nakkiBooking->setEvent(null);
            }
        }

        return $this;
    }

    public function getNakkikoneEnabled(): ?bool
    {
        return $this->NakkikoneEnabled;
    }

    public function setNakkikoneEnabled(bool $NakkikoneEnabled): self
    {
        $this->NakkikoneEnabled = $NakkikoneEnabled;

        return $this;
    }

    public function getNakkiInfoFi(): ?string
    {
        return $this->nakkiInfoFi;
    }

    public function setNakkiInfoFi(?string $nakkiInfoFi): self
    {
        $this->nakkiInfoFi = $nakkiInfoFi;

        return $this;
    }

    public function getNakkiInfoEn(): ?string
    {
        return $this->nakkiInfoEn;
    }

    public function setNakkiInfoEn(?string $nakkiInfoEn): self
    {
        $this->nakkiInfoEn = $nakkiInfoEn;

        return $this;
    }

    public function getIncludeSaferSpaceGuidelines(): ?bool
    {
        return $this->includeSaferSpaceGuidelines;
    }

    public function setIncludeSaferSpaceGuidelines(?bool $includeSaferSpaceGuidelines): self
    {
        $this->includeSaferSpaceGuidelines = $includeSaferSpaceGuidelines;

        return $this;
    }

    public function getRSVPEmailSubject(): ?string
    {
        return $this->RSVPEmailSubject;
    }

    public function setRSVPEmailSubject(?string $RSVPEmailSubject): self
    {
        $this->RSVPEmailSubject = $RSVPEmailSubject;

        return $this;
    }

    public function getRSVPEmailBody(): ?string
    {
        return $this->RSVPEmailBody;
    }

    public function setRSVPEmailBody(?string $RSVPEmailBody): self
    {
        $this->RSVPEmailBody = $RSVPEmailBody;

        return $this;
    }

    public function getHeaderTheme(): ?string
    {
        return $this->headerTheme;
    }

    public function setHeaderTheme(?string $headerTheme): self
    {
        $this->headerTheme = $headerTheme;

        return $this;
    }

    public function getStreamPlayerUrl(): ?string
    {
        return $this->streamPlayerUrl;
    }

    public function setStreamPlayerUrl(?string $streamPlayerUrl): self
    {
        $this->streamPlayerUrl = $streamPlayerUrl;

        return $this;
    }

    public function getImgFilterColor(): ?string
    {
        return $this->imgFilterColor;
    }

    public function setImgFilterColor(?string $imgFilterColor): self
    {
        $this->imgFilterColor = $imgFilterColor;

        return $this;
    }

    public function getImgFilterBlendMode(): ?string
    {
        return $this->imgFilterBlendMode;
    }

    public function setImgFilterBlendMode(?string $imgFilterBlendMode): self
    {
        $this->imgFilterBlendMode = $imgFilterBlendMode;

        return $this;
    }

    public function getArtistSignUpEnabled(): ?bool
    {
        return $this->artistSignUpEnabled;
    }

    public function setArtistSignUpEnabled(?bool $artistSignUpEnabled): self
    {
        $this->artistSignUpEnabled = $artistSignUpEnabled;

        return $this;
    }
}
