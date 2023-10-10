<?php

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia as Media;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Gedmo\Mapping\Annotation as Gedmo;

use function Symfony\Component\String\u;

#[ORM\Entity(repositoryClass: \App\Repository\EventRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'event')]
class Event implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $Name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $Nimi = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $EventDate = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface|\DateTime $publishDate;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    private ?\App\Entity\Sonata\SonataMediaMedia $picture = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $css = "/* If you want to play with CSS these help you. First remove this and last line
body {
    background-image: url(/images/bg_stripe_transparent.png);
    background-color: yellow;
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $Content = "Use these: <br>
            {{ timetable }} <br> {{ timetable_to_page }} <br> {{ bios }} <br> {{ vj_bios }} <br> {{ rsvp }} <br> {{ links }} <br> {{ happening_list }}";

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $Sisallys = "Käytä näitä, vaikka monta kertaa: <br>
            {{ timetable }} <br> {{ timetable_to_page }} <br> {{ bios }} <br> {{ vj_bios }} <br> {{ rsvp }} <br> {{ links }} <br> {{ happening_list }}";

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: 'boolean')]
    private bool $published = false;

    #[ORM\Column(type: 'string', length: 180)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $epics = null;

    #[ORM\Column(type: 'boolean')]
    private bool $externalUrl = false;

    #[ORM\Column(type: 'boolean')]
    private bool $sticky = false;

    #[ORM\Column(type: 'string', length: 255)]
    private string $picturePosition = 'banner';

    #[ORM\Column(type: 'boolean')]
    private bool $cancelled = false;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    private ?\App\Entity\Sonata\SonataMediaMedia $attachment = null;

    #[ORM\Column(type: 'array', nullable: true)]
    private ?array $links = [];

    #[ORM\OneToMany(targetEntity: EventArtistInfo::class, mappedBy: \Event::class)]
    #[ORM\OrderBy(['stage' => 'ASC', 'StartTime' => 'ASC'])]
    private $eventArtistInfos;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $until = null;

    #[ORM\OneToMany(targetEntity: RSVP::class, mappedBy: 'event', orphanRemoval: true)]
    private $RSVPs;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $rsvpSystemEnabled = false;

    #[ORM\OneToMany(targetEntity: Nakki::class, mappedBy: 'event', orphanRemoval: true)]
    #[OrderBy(["startAt" => "ASC"])]
    private $nakkis;

    #[ORM\OneToMany(targetEntity: NakkiBooking::class, mappedBy: 'event', orphanRemoval: true)]
    #[OrderBy(["startAt" => "ASC"])]
    private $nakkiBookings;

    #[ORM\Column(type: 'boolean')]
    private bool $NakkikoneEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nakkiInfoFi =
    '
        <p>Valitse vähintään 2 tunnin Nakkia sekä purku tai roudaus</p>
        <h6>Saat ilmaisen sisäänpääsyn</h6>
        ';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nakkiInfoEn =
    '
        <p>Choose at least two (2) Nakkis that are 1 hour length and build up or take down</p>
        <h6>You\'ll get free entry to the party</h6>
        ';

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $includeSaferSpaceGuidelines = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $headerTheme = 'light';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $streamPlayerUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imgFilterColor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imgFilterBlendMode = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $artistSignUpEnabled = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $artistSignUpEnd = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $artistSignUpStart = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $webMeetingUrl = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $showArtistSignUpOnlyForLoggedInMembers = false;

    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'event', orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'DESC'])]
    private $tickets;

    #[ORM\Column(type: 'integer')]
    private int $ticketCount = 0;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $ticketsEnabled = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $ticketPrice = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ticketInfoFi = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ticketInfoEn = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $ticketPresaleStart = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ticketPresaleEnd = null;

    #[ORM\Column(type: 'integer')]
    private int $ticketPresaleCount = 0;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $showNakkikoneLinkInEvent = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $requireNakkiBookingsToBeDifferentTimes = true;

    #[ORM\OneToMany(targetEntity: Email::class, mappedBy: 'event')]
    private $emails;

    #[ORM\Column(nullable: true)]
    private ?bool $rsvpOnlyToActiveMembers = null;

    #[ORM\Column(nullable: true)]
    private ?bool $nakkiRequiredForTicketReservation = false;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $backgroundEffect = null;

    #[ORM\Column(nullable: true)]
    private ?int $backgroundEffectOpacity = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $backgroundEffectPosition = null;

    #[ORM\Column]
    private ?bool $artistSignUpAskSetLength = true;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Notification::class)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Happening::class)]
    #[OrderBy(['time' => 'ASC'])]
    private Collection $happenings;

    #[ORM\ManyToMany(targetEntity: Member::class)]
    private Collection $nakkiResponsibleAdmin;

    #[ORM\Column(nullable: true)]
    private ?bool $allowMembersToCreateHappenings = true;

    #[ORM\ManyToOne]
    private ?Location $location = null;

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
    public function __toString(): string
    {
        return $this->getName() ?: 'Happening';
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
        $this->eventArtistInfos = new ArrayCollection();
        $this->RSVPs = new ArrayCollection();
        $this->nakkis = new ArrayCollection();
        $this->nakkiBookings = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->emails = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->happenings = new ArrayCollection();
        $this->nakkiResponsibleAdmin = new ArrayCollection();
    }
    public function getNowTest(): ?string
    {
        $now = new \DateTime();
        if ($this->until) {
            if ($now >= $this->EventDate && $now <= $this->until) {
                return 'now';
            } elseif ($now > $this->until) {
                return 'after';
            } elseif ($now < $this->EventDate) {
                return 'before';
            }
        } else {
            if ($now < $this->EventDate) {
                return 'before';
            } else {
                return 'after';
            }
        }
        return 'undefined';
    }
    public function isInPast(): bool
    {
        if ($this->getNowTest() == 'after') {
            return true;
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
    public function getContentByLang($lang)
    {
        if ($lang == 'fi') {
            $abstract = $this->removeTwigTags($this->Sisallys);
        } else {
            $abstract = $this->removeTwigTags($this->Content);
        }
        return html_entity_decode(strip_tags((string) $abstract));
    }
    public function getAbstract($lang)
    {
        if ($lang == 'fi') {
            $abstract = $this->removeTwigTags($this->Sisallys);
        } else {
            $abstract = $this->removeTwigTags($this->Content);
        }
        return u(html_entity_decode(strip_tags((string) $abstract)))->truncate(150, '..');
    }
    protected function removeTwigTags($message)
    {
        $abstract = str_replace("{{ bios }}", "", (string) $message);
        $abstract = str_replace("{{ timetable }}", "", $abstract);
        $abstract = str_replace("{{ timetable_to_page }}", "", $abstract);
        $abstract = str_replace("{{ vj_bios }}", "", $abstract);
        $abstract = str_replace("{{ rsvp }}", "", $abstract);
        $abstract = str_replace("{{ links }}", "", $abstract);
        $abstract = str_replace("{{ streamplayer }}", "", $abstract);
        $abstract = str_replace("{{ ticket }}", "", $abstract);
        $abstract = str_replace("{{ art_artist_list }}", "", $abstract);
        $abstract = str_replace("{{ happening_list }}", "", $abstract);
        return $abstract;
    }
    public function getNameByLang($lang)
    {
        if ($lang == 'fi') {
            return $this->Nimi;
        } else {
            return $this->Name;
        }
    }
    public function getNameAndDateByLang($lang)
    {
        if ($lang == 'fi') {
            return $this->Nimi . ' - ' . $this->EventDate->format('j.n.Y, H:i');
        } else {
            return $this->Name . ' - ' . $this->EventDate->format('j.n.Y, H:i');
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

    public function isRsvpSystemEnabled(): ?bool
    {
        return $this->rsvpSystemEnabled;
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

    public function getArtistSignUpEnd(): ?\DateTimeImmutable
    {
        return $this->artistSignUpEnd;
    }

    public function setArtistSignUpEnd(?\DateTimeImmutable $artistSignUpEnd): self
    {
        $this->artistSignUpEnd = $artistSignUpEnd;

        return $this;
    }

    public function getArtistSignUpStart(): ?\DateTimeImmutable
    {
        return $this->artistSignUpStart;
    }

    public function setArtistSignUpStart(?\DateTimeImmutable $artistSignUpStart): self
    {
        $this->artistSignUpStart = $artistSignUpStart;

        return $this;
    }
    public function getArtistSignUpNow(): bool
    {
        $now = new \DateTimeImmutable('now');
        if (
            $this->getArtistSignUpEnabled() &&
            $this->getArtistSignUpStart() <= $now &&
            $this->getArtistSignUpEnd() >= $now
        ) {
            return true;
        }
        return false;
    }

    public function getWebMeetingUrl(): ?string
    {
        return $this->webMeetingUrl;
    }

    public function setWebMeetingUrl(?string $webMeetingUrl): self
    {
        $this->webMeetingUrl = $webMeetingUrl;

        return $this;
    }

    public function getShowArtistSignUpOnlyForLoggedInMembers(): ?bool
    {
        return $this->showArtistSignUpOnlyForLoggedInMembers;
    }

    public function setShowArtistSignUpOnlyForLoggedInMembers(?bool $showArtistSignUpOnlyForLoggedInMembers): self
    {
        $this->showArtistSignUpOnlyForLoggedInMembers = $showArtistSignUpOnlyForLoggedInMembers;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): self
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets[] = $ticket;
            $ticket->setEvent($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): self
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getEvent() === $this) {
                $ticket->setEvent(null);
            }
        }

        return $this;
    }

    public function getTicketCount(): ?int
    {
        return $this->ticketCount;
    }

    public function setTicketCount(int $ticketCount): self
    {
        $this->ticketCount = $ticketCount;

        return $this;
    }

    public function getTicketsEnabled(): ?bool
    {
        return $this->ticketsEnabled;
    }

    public function setTicketsEnabled(?bool $ticketsEnabled): self
    {
        $this->ticketsEnabled = $ticketsEnabled;

        return $this;
    }

    public function getTicketInfo($lang): ?string
    {
        $func = 'ticketInfo' . ucfirst((string) $lang);
        return $this->{$func};
    }
    public function getNakkiInfo($lang): ?string
    {
        $func = 'nakkiInfo' . ucfirst((string) $lang);
        return $this->{$func};
    }

    public function getTicketPrice(): ?int
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(?int $ticketPrice): self
    {
        $this->ticketPrice = $ticketPrice;

        return $this;
    }

    public function getMultiday(): bool
    {
        //dd( $this->EventDate->format('U') - $this->until->format('U'));
        if ($this->until) {
            if (($this->until->format('U') - $this->EventDate->format('U')) > 86400) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getMusicArtistInfos(): array
    {
        $bystage = [];
        foreach ($this->eventArtistInfos as $info) {
            if (!is_null($info->getStartTime())) {
                if ($info->getArtistClone()->getType() == 'DJ' || $info->getArtistClone()->getType() == 'Live') {
                    $bystage[$info->getStage()][] = $info;
                }
            }
        }
        return $bystage;
    }
    public function getArtistInfosByType(string $type): array
    {
        $bystage = [];
        foreach ($this->eventArtistInfos as $info) {
            if (!is_null($info->getStartTime())) {
                if ($info->getArtistClone()->getType() == $type) {
                    $bystage[$info->getStage()][] = $info;
                }
            }
        }
        return $bystage;
    }

    public function getTicketInfoFi(): ?string
    {
        return $this->ticketInfoFi;
    }

    public function setTicketInfoFi(?string $ticketInfoFi): self
    {
        $this->ticketInfoFi = $ticketInfoFi;

        return $this;
    }

    public function getTicketInfoEn(): ?string
    {
        return $this->ticketInfoEn;
    }

    public function setTicketInfoEn(?string $ticketInfoEn): self
    {
        $this->ticketInfoEn = $ticketInfoEn;

        return $this;
    }

    public function getTicketPresaleStart(): ?\DateTimeImmutable
    {
        return $this->ticketPresaleStart;
    }

    public function setTicketPresaleStart(?\DateTimeImmutable $ticketPresaleStart): self
    {
        $this->ticketPresaleStart = $ticketPresaleStart;

        return $this;
    }

    public function getTicketPresaleEnd(): ?\DateTimeImmutable
    {
        return $this->ticketPresaleEnd;
    }

    public function setTicketPresaleEnd(?\DateTimeImmutable $ticketPresaleEnd): self
    {
        $this->ticketPresaleEnd = $ticketPresaleEnd;

        return $this;
    }

    public function ticketPresaleEnabled()
    {
        $now = new \DateTime('now');
        if (
            is_object($this->ticketPresaleStart) && $this->ticketPresaleStart <= $now &&
            is_object($this->ticketPresaleEnd) && $this->ticketPresaleEnd >= $now
        ) {
            return true;
        }
        return false;
    }

    public function getTicketPresaleCount(): ?int
    {
        return $this->ticketPresaleCount;
    }

    public function setTicketPresaleCount(int $ticketPresaleCount): self
    {
        $this->ticketPresaleCount = $ticketPresaleCount;

        return $this;
    }

    public function getShowNakkikoneLinkInEvent(): ?bool
    {
        return $this->showNakkikoneLinkInEvent;
    }

    public function setShowNakkikoneLinkInEvent(?bool $showNakkikoneLinkInEvent): self
    {
        $this->showNakkikoneLinkInEvent = $showNakkikoneLinkInEvent;

        return $this;
    }

    public function getRequireNakkiBookingsToBeDifferentTimes(): ?bool
    {
        return $this->requireNakkiBookingsToBeDifferentTimes;
    }

    public function setRequireNakkiBookingsToBeDifferentTimes(?bool $requireNakkiBookingsToBeDifferentTimes): self
    {
        $this->requireNakkiBookingsToBeDifferentTimes = $requireNakkiBookingsToBeDifferentTimes;

        return $this;
    }
    public function responsibleMemberNakkis(Member $member): array
    {
        $bookings = [];
        foreach ($this->getNakkis() as $nakki) {
            if ($nakki->getResponsible() == $member || in_array('ROLE_SUPER_ADMIN', $member->getUser()->getRoles()) || $this->nakkiResponsibleAdmin->contains($member)) {
                $bookings[$nakki->getDefinition()->getName($member->getLocale())]['b'][] = $nakki->getNakkiBookings();
                $bookings[$nakki->getDefinition()->getName($member->getLocale())]['mattermost'] = $nakki->getMattermostChannel();
                $bookings[$nakki->getDefinition()->getName($member->getLocale())]['responsible'] = $nakki->getResponsible();
            }
        }
        return $bookings;
    }
    public function memberNakkis(Member $member): array
    {
        $bookings = [];
        $booking = $member->getEventNakkiBooking($this);
        if ($booking) {
            $nakki = $booking->getNakki();
            $bookings[$nakki->getDefinition()->getName($member->getLocale())]['b'][] = $nakki->getNakkiBookings();
            $bookings[$nakki->getDefinition()->getName($member->getLocale())]['mattermost'] = $nakki->getMattermostChannel();
            $bookings[$nakki->getDefinition()->getName($member->getLocale())]['responsible'] = $nakki->getResponsible();
        }
        return $bookings;
    }

    public function getAllNakkiResponsibles(string $locale): array
    {
        $responsibles = [];
        foreach ($this->getNakkis() as $nakki) {
            $responsibles[$nakki->getDefinition()->getName($locale)]['mattermost'] = $nakki->getMattermostChannel();
            $responsibles[$nakki->getDefinition()->getName($locale)]['responsible'] = $nakki->getResponsible();
        }
        return $responsibles;
    }
    /**
     * @return Collection<int, Email>
     */
    public function getEmails(): Collection
    {
        return $this->emails;
    }

    public function addEmail(Email $email): self
    {
        if (!$this->emails->contains($email)) {
            $this->emails[] = $email;
            $email->setEvent($this);
        }

        return $this;
    }

    public function removeEmail(Email $email): self
    {
        if ($this->emails->removeElement($email)) {
            // set the owning side to null (unless already changed)
            if ($email->getEvent() === $this) {
                $email->setEvent(null);
            }
        }

        return $this;
    }
    public function getBadgeText(): string
    {
        if ($this->type == 'announcement') {
            return 'Announcement';
        } else {
            if ($this->getNowTest() == 'now') {
                return 'event.now';
            }
            if ($this->getNowTest() == 'after') {
                return 'event.after';
            }
            return 'event.in_future';
        }
    }

    public function isRsvpOnlyToActiveMembers(): ?bool
    {
        return $this->rsvpOnlyToActiveMembers;
    }

    public function setRsvpOnlyToActiveMembers(?bool $rsvpOnlyToActiveMembers): self
    {
        $this->rsvpOnlyToActiveMembers = $rsvpOnlyToActiveMembers;

        return $this;
    }

    public function isNakkiRequiredForTicketReservation(): ?bool
    {
        return $this->nakkiRequiredForTicketReservation;
    }

    public function setNakkiRequiredForTicketReservation(?bool $nakkiRequiredForTicketReservation): self
    {
        $this->nakkiRequiredForTicketReservation = $nakkiRequiredForTicketReservation;

        return $this;
    }

    public function getBackgroundEffect(): ?string
    {
        return $this->backgroundEffect;
    }

    public function setBackgroundEffect(?string $backgroundEffect): self
    {
        $this->backgroundEffect = $backgroundEffect;

        return $this;
    }

    public function getBackgroundEffectOpacity(): ?int
    {
        return $this->backgroundEffectOpacity;
    }

    public function setBackgroundEffectOpacity(?int $backgroundEffectOpacity): self
    {
        $this->backgroundEffectOpacity = $backgroundEffectOpacity;

        return $this;
    }

    public function getBackgroundEffectPosition(): ?string
    {
        return $this->backgroundEffectPosition;
    }

    public function setBackgroundEffectPosition(?string $backgroundEffectPosition): self
    {
        $this->backgroundEffectPosition = $backgroundEffectPosition;

        return $this;
    }

    public function isArtistSignUpAskSetLength(): ?bool
    {
        return $this->artistSignUpAskSetLength;
    }

    public function setArtistSignUpAskSetLength(bool $artistSignUpAskSetLength): self
    {
        $this->artistSignUpAskSetLength = $artistSignUpAskSetLength;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setEvent($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getEvent() === $this) {
                $notification->setEvent(null);
            }
        }

        return $this;
    }
    public function getUrlByLang($lang): string
    {
        if ($this->externalUrl && $this->url) {
            return $this->url;
        }
        $year = '/' . $this->EventDate->format('Y');
        $url = '/' . $this->url;
        $event = '/en/event/';

        if ($lang == 'fi') {
            $event = '/tapahtuma/';
            $lang = '';
        }

        if (empty($this->url) && !$this->externalUrl) {
            return 'https://entropy.fi' . $event . $this->id;
        }
        return $lang ? 'https://entropy.fi/' . $lang . $year . $url : 'https://entropy.fi' . $year . $url;
    }

    /**
     * @return Collection<int, Happening>
     */
    public function getHappenings(): Collection
    {
        return $this->happenings;
    }

    public function addHappening(Happening $happening): self
    {
        if (!$this->happenings->contains($happening)) {
            $this->happenings->add($happening);
            $happening->setEvent($this);
        }

        return $this;
    }

    public function removeHappening(Happening $happening): self
    {
        if ($this->happenings->removeElement($happening)) {
            // set the owning side to null (unless already changed)
            if ($happening->getEvent() === $this) {
                $happening->setEvent(null);
            }
        }

        return $this;
    }

    public function ticketHolderHasNakki($member): ?NakkiBooking
    {
        if ($this->isNakkiRequiredForTicketReservation()) {
            foreach ($this->getNakkiBookings() as $booking) {
                if ($booking->getMember() == $member) {
                    return $booking;
                }
            }
        }
        return null;
    }

    /**
     * @return Collection<int, Member>
     */
    public function getNakkiResponsibleAdmin(): Collection
    {
        return $this->nakkiResponsibleAdmin;
    }

    public function addNakkiResponsibleAdmin(Member $nakkiResponsibleAdmin): static
    {
        if (!$this->nakkiResponsibleAdmin->contains($nakkiResponsibleAdmin)) {
            $this->nakkiResponsibleAdmin->add($nakkiResponsibleAdmin);
        }

        return $this;
    }

    public function removeNakkiResponsibleAdmin(Member $nakkiResponsibleAdmin): static
    {
        $this->nakkiResponsibleAdmin->removeElement($nakkiResponsibleAdmin);

        return $this;
    }

    public function isAllowMembersToCreateHappenings(): ?bool
    {
        return $this->allowMembersToCreateHappenings;
    }

    public function setAllowMembersToCreateHappenings(?bool $allowMembersToCreateHappenings): static
    {
        $this->allowMembersToCreateHappenings = $allowMembersToCreateHappenings;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;

        return $this;
    }
}
