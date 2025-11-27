<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia as Media;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Symfony\Component\String\AbstractString;

use function Symfony\Component\String\u;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'event')]
class Event implements \Stringable
{
    private const array LEGACY_TAG_MAP = [
        '{{ timetable_to_page_with_genre }}' => '{{ dj_timetable }}',
        '{{ timetable_with_genre }}' => '{{ dj_timetable }}',
        '{{ timetable_to_page }}' => '{{ dj_timetable }}',
        '{{ timetable }}' => '{{ dj_timetable }}',
        '{{ bios }}' => '{{ dj_bio }}',
        '{{ vj_bios }}' => '{{ vj_bio }}',
        '{{ vj_timetable_to_page }}' => '{{ vj_timetable }}',
    ];

    private const array CONTENT_TWIG_TAGS = [
        '{{ dj_timetable }}',
        '{{ vj_timetable }}',
        '{{ dj_bio }}',
        '{{ vj_bio }}',
        '{{ streamplayer }}',
        '{{ links }}',
        '{{ rsvp }}',
        '{{ stripe_ticket }}',
        '{{ ticket }}',
        '{{ art_artist_list }}',
        '{{ happening_list }}',
        '{{ menu }}',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $Name = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $Nimi = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $EventDate;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishDate = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    private ?Media $picture = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $css = '/* If you want to play with CSS these help you. First remove this and last line
[data-bs-theme=dark] body, body {
    background-image: url(/images/bg_stripe_transparent.png);
    background-color: yellow;
}
[data-bs-theme=dark].e-container, .e-container {
    background: #220101;
    color: red;
}
*/';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $Content = 'Use these: <br>
            {{ dj_timetable }} <br> {{ vj_timetable }} <br> {{ dj_bio }} <br> {{ vj_bio }} <br> {{ rsvp }} <br> {{ links }} <br> {{ happening_list }}';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $Sisallys = 'Käytä näitä, vaikka monta kertaa: <br>
            {{ dj_timetable }} <br> {{ vj_timetable }} <br> {{ dj_bio }} <br> {{ vj_bio }} <br> {{ rsvp }} <br> {{ links }} <br> {{ happening_list }}';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $published = false;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $type = '';

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    private ?string $epics = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $externalUrl = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $sticky = false;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $picturePosition = 'banner';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $cancelled = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $multiday = false;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    private ?Media $attachment = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $links = [];

    #[ORM\Embedded(class: ArtistDisplayConfiguration::class, columnPrefix: false)]
    private ArtistDisplayConfiguration $artistDisplayConfiguration;

    /**
     * @var Collection<int, EventArtistInfo>
     */
    #[
        ORM\OneToMany(
            targetEntity: EventArtistInfo::class,
            mappedBy: \Event::class,
        ),
    ]
    #[OrderBy(['stage' => 'ASC', 'StartTime' => 'ASC'])]
    private Collection $eventArtistInfos;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $until = null;

    /**
     * @var Collection<int, RSVP>
     */
    #[
        ORM\OneToMany(
            targetEntity: RSVP::class,
            mappedBy: 'event',
            orphanRemoval: true,
        ),
    ]
    private Collection $RSVPs;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $rsvpSystemEnabled = false;

    /**
     * @var Collection<int, Nakki>
     */
    #[
        ORM\OneToMany(
            targetEntity: Nakki::class,
            mappedBy: 'event',
            orphanRemoval: true,
        ),
    ]
    #[OrderBy(['startAt' => 'ASC'])]
    private Collection $nakkis;

    /**
     * @var Collection<int, NakkiBooking>
     */
    #[
        ORM\OneToMany(
            targetEntity: NakkiBooking::class,
            mappedBy: 'event',
            orphanRemoval: true,
        ),
    ]
    #[OrderBy(['startAt' => 'ASC'])]
    private Collection $nakkiBookings;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $NakkikoneEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nakkiInfoFi = '
        <p>Valitse vähintään 2 tunnin Nakkia sekä purku tai roudaus</p>
        <h6>Saat ilmaisen sisäänpääsyn</h6>
        ';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nakkiInfoEn = '
        <p>Choose at least two (2) Nakkis that are 1 hour length and build up or take down</p>
        <h6>You\'ll get free entry to the party</h6>
        ';

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $includeSaferSpaceGuidelines = false;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $headerTheme = 'light';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $streamPlayerUrl = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $imgFilterColor = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $imgFilterBlendMode = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $artistSignUpEnabled = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $artistSignUpEnd = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $artistSignUpStart = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $webMeetingUrl = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $showArtistSignUpOnlyForLoggedInMembers = false;

    /**
     * @var Collection<int, Ticket>
     */
    #[
        ORM\OneToMany(
            targetEntity: Ticket::class,
            mappedBy: 'event',
            orphanRemoval: true,
        ),
    ]
    #[OrderBy(['id' => 'DESC'])]
    private Collection $tickets;

    #[ORM\Column(type: Types::INTEGER)]
    private int $ticketCount = 0;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $ticketsEnabled = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $ticketPrice = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ticketInfoFi = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ticketInfoEn = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ticketPresaleStart = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ticketPresaleEnd = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $ticketPresaleCount = 0;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $showNakkikoneLinkInEvent = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $requireNakkiBookingsToBeDifferentTimes = true;

    /**
     * @var Collection<int, Email>
     */
    #[ORM\OneToMany(targetEntity: Email::class, mappedBy: 'event')]
    private Collection $emails;

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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $backgroundEffectConfig = null;

    #[ORM\Column]
    private bool $artistSignUpAskSetLength = true;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Notification::class)]
    private Collection $notifications;

    /**
     * @var Collection<int, Happening>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Happening::class)]
    #[OrderBy(['time' => 'ASC'])]
    private Collection $happenings;

    /**
     * @var Collection<int, Member>
     */
    #[ORM\ManyToMany(targetEntity: Member::class)]
    private Collection $nakkiResponsibleAdmin;

    #[ORM\Column(nullable: true)]
    private ?bool $allowMembersToCreateHappenings = true;

    #[ORM\ManyToOne]
    private ?Location $location = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $template = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $abstractFi = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $abstractEn = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Product::class)]
    #[OrderBy(['amount' => 'ASC'])]
    private Collection $products;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $artistSignUpInfoFi = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $artistSignUpInfoEn = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(nullable: true)]
    private ?bool $sendRsvpEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkToForums = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $wikiPage = null;

    #[ORM\Column(nullable: true)]
    private ?int $ticketTotalAmount = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->version = ($this->version ?? 0) + 1;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->Name;
    }

    public function setName(string $Name): self
    {
        $this->Name = $Name;

        return $this;
    }

    public function getNimi(): string
    {
        return $this->Nimi;
    }

    public function setNimi(string $Nimi): self
    {
        $this->Nimi = $Nimi;

        return $this;
    }

    public function getEventDate(): \DateTimeImmutable
    {
        return $this->EventDate;
    }

    public function setEventDate(\DateTimeInterface $EventDate): self
    {
        $this->EventDate = $EventDate instanceof \DateTimeImmutable
            ? $EventDate
            : \DateTimeImmutable::createFromInterface($EventDate);

        return $this;
    }

    public function getPublishDate(): ?\DateTimeImmutable
    {
        return $this->publishDate;
    }

    public function setPublishDate(?\DateTimeImmutable $publishDate): self
    {
        // Null = draft/unpublished; domain service (EventTemporalStateService) interprets accordingly.
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

    #[\Override]
    public function __toString(): string
    {
        return $this->Name ?: 'Happening';
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

    /**
     * Returns true if the event is marked as external.
     */
    public function isExternalUrl(): bool
    {
        return $this->externalUrl;
    }

    // (Removed 2025-10-02) Publication logic migrated to App\Domain\EventTemporalStateService::isPublished(Event) for clock-driven determinism.

    /**
     * Returns true if the event is marked as cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Returns true if the event is marked as sticky.
     */
    public function isSticky(): bool
    {
        return $this->sticky;
    }

    /**
     * Returns true if the event is marked as multiday.
     */
    public function isMultiday(): bool
    {
        return $this->multiday;
    }

    /**
     * Returns true if the event has Nakkikone enabled.
     */
    public function isNakkikoneEnabled(): bool
    {
        return $this->NakkikoneEnabled;
    }

    /**
     * Returns true if artist sign-up is enabled for the event.
     */
    public function isArtistSignUpEnabled(): bool
    {
        return (bool) $this->artistSignUpEnabled;
    }

    /**
     * Returns true if RSVP system is enabled.
     */
    public function isRsvpSystemEnabled(): bool
    {
        return (bool) $this->rsvpSystemEnabled;
    }

    /**
     * Returns true if tickets are enabled.
     */
    public function isTicketsEnabled(): bool
    {
        return (bool) $this->ticketsEnabled;
    }

    /**
     * Returns true if showNakkikoneLinkInEvent is enabled.
     */
    public function isShowNakkikoneLinkInEvent(): bool
    {
        return (bool) $this->showNakkikoneLinkInEvent;
    }

    /**
     * Returns true if requireNakkiBookingsToBeDifferentTimes is enabled.
     */
    public function isRequireNakkiBookingsToBeDifferentTimes(): bool
    {
        return (bool) $this->requireNakkiBookingsToBeDifferentTimes;
    }

    /**
     * Returns true if allowMembersToCreateHappenings is enabled.
     */
    public function isAllowMembersToCreateHappenings(): bool
    {
        return (bool) $this->allowMembersToCreateHappenings;
    }

    /**
     * Returns true if sendRsvpEmail is enabled.
     */
    public function isSendRsvpEmail(): bool
    {
        return (bool) $this->sendRsvpEmail;
    }

    /**
     * Returns true if includeSaferSpaceGuidelines is enabled.
     */
    public function isIncludeSaferSpaceGuidelines(): bool
    {
        return (bool) $this->includeSaferSpaceGuidelines;
    }

    /**
     * Returns true if rsvpOnlyToActiveMembers is enabled.
     */
    public function isRsvpOnlyToActiveMembers(): bool
    {
        return (bool) $this->rsvpOnlyToActiveMembers;
    }

    /**
     * Returns true if nakkiRequiredForTicketReservation is enabled.
     */
    public function isNakkiRequiredForTicketReservation(): bool
    {
        return (bool) $this->nakkiRequiredForTicketReservation;
    }

    /**
     * Returns true if artistSignUpAskSetLength is enabled.
     */
    public function isArtistSignUpAskSetLength(): bool
    {
        return $this->artistSignUpAskSetLength;
    }

    /**
     * Returns true if artist sign-up is only shown for logged-in members.
     */
    public function isShowArtistSignUpOnlyForLoggedInMembers(): bool
    {
        return (bool) $this->showArtistSignUpOnlyForLoggedInMembers;
    }

    public function __construct()
    {
        // publishDate intentionally NOT initialized here (null => draft state)
        $this->updatedAt = new \DateTimeImmutable();
        $this->EventDate = new \DateTimeImmutable('+2weeks');
        $this->eventArtistInfos = new ArrayCollection();
        $this->RSVPs = new ArrayCollection();
        $this->nakkis = new ArrayCollection();
        $this->nakkiBookings = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->emails = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->happenings = new ArrayCollection();
        $this->nakkiResponsibleAdmin = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->artistDisplayConfiguration = new ArtistDisplayConfiguration();
    }

    public function getType(): string
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

    public function getContentForTwig($lang): ?string
    {
        $content =
            'fi' == $lang ? $this->Sisallys : $this->Content;

        return $this->normalizeLegacyTwigTags($content);
    }

    public function getContentByLang($lang): string
    {
        $abstract =
            'fi' == $lang
                ? $this->removeTwigTags($this->Sisallys)
                : $this->removeTwigTags($this->Content);

        return html_entity_decode(strip_tags($abstract));
    }

    public function getAbstractFromContent($lang): AbstractString
    {
        $abstract =
            'fi' == $lang
                ? $this->removeTwigTags($this->Sisallys)
                : $this->removeTwigTags($this->Content);

        return u(html_entity_decode(strip_tags($abstract)))->truncate(
            200,
            '..',
        );
    }

    protected function removeTwigTags($message): string
    {
        $normalized = $this->normalizeLegacyTwigTags((string) $message) ?? '';

        return str_replace(self::CONTENT_TWIG_TAGS, '', $normalized);
    }

    private function normalizeLegacyTwigTags(?string $content): ?string
    {
        if (null === $content) {
            return null;
        }

        return str_replace(
            array_keys(self::LEGACY_TAG_MAP),
            array_values(self::LEGACY_TAG_MAP),
            $content,
        );
    }

    public function getNameByLang($lang): string
    {
        if ('fi' == $lang) {
            return $this->Nimi;
        } else {
            return $this->Name;
        }
    }

    public function getNameAndDateByLang($lang): string
    {
        if ('fi' == $lang) {
            return $this->Nimi.' - '.$this->EventDate->format('j.n.Y, H:i');
        } else {
            return $this->Name.' - '.$this->EventDate->format('j.n.Y, H:i');
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

    public function removeEventArtistInfo(
        EventArtistInfo $eventArtistInfo,
    ): self {
        if ($this->eventArtistInfos->contains($eventArtistInfo)) {
            $this->eventArtistInfos->removeElement($eventArtistInfo);
            // set the owning side to null (unless already changed)
            if ($eventArtistInfo->getEvent() === $this) {
                $eventArtistInfo->setEvent(null);
            }
        }

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUntil(): ?\DateTimeImmutable
    {
        if ($this->until instanceof \DateTimeImmutable) {
            return $this->until;
        }

        $interval = 'meeting' === $this->type ? 'PT2H' : 'PT8H';

        return $this->EventDate->add(new \DateInterval($interval));
    }

    public function getExplicitUntil(): ?\DateTimeImmutable
    {
        return $this->until;
    }

    public function setUntil(?\DateTimeInterface $until): self
    {
        $this->until = $until instanceof \DateTimeImmutable
            ? $until
            : ($until instanceof \DateTime ? \DateTimeImmutable::createFromInterface($until) : null);

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
        $this->RSVPs->removeElement($rSVP);

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
        $this->nakkis->removeElement($nakki);

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
        $this->nakkiBookings->removeElement($nakkiBooking);

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

    public function setIncludeSaferSpaceGuidelines(
        ?bool $includeSaferSpaceGuidelines,
    ): self {
        $this->includeSaferSpaceGuidelines = $includeSaferSpaceGuidelines;

        return $this;
    }

    public function getHeaderTheme(): string
    {
        return $this->headerTheme;
    }

    public function setHeaderTheme(string $headerTheme): self
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

    public function setArtistSignUpEnd(
        ?\DateTimeImmutable $artistSignUpEnd,
    ): self {
        $this->artistSignUpEnd = $artistSignUpEnd;

        return $this;
    }

    public function getArtistSignUpStart(): ?\DateTimeImmutable
    {
        return $this->artistSignUpStart;
    }

    public function setArtistSignUpStart(
        ?\DateTimeImmutable $artistSignUpStart,
    ): self {
        $this->artistSignUpStart = $artistSignUpStart;

        return $this;
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

    public function setShowArtistSignUpOnlyForLoggedInMembers(
        ?bool $showArtistSignUpOnlyForLoggedInMembers,
    ): self {
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
        $this->tickets->removeElement($ticket);

        return $this;
    }

    public function memberHasTicket(Member $member): bool
    {
        foreach ($this->getTickets() as $ticket) {
            if ($ticket->getOwner() == $member) {
                return true;
            }
        }

        return false;
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
        $func = 'ticketInfo'.ucfirst((string) $lang);

        return $this->{$func};
    }

    public function getNakkiInfo($lang): ?string
    {
        $func = 'nakkiInfo'.ucfirst((string) $lang);

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
        // If explicitly flagged true (factory override), short-circuit.
        if ($this->multiday) {
            return true;
        }

        if ($this->until instanceof \DateTimeInterface) {
            return $this->until->format('U') - $this->EventDate->format('U') >
                86400;
        }

        return false;
    }

    /**
     * Explicit multiday setter for factory/state hydration.
     *
     * NOTE: multiday remains a derived concept (eventDate vs until) unless
     * explicitly forced true via this setter. Setting false defers back to
     * derived computation so that adjusting dates still reflects reality.
     */
    public function setMultiday(bool $multiday): self
    {
        $this->multiday = $multiday;

        return $this;
    }

    public function getMusicArtistInfos(): array
    {
        $bystage = [];
        foreach ($this->eventArtistInfos as $info) {
            if (
                null !== $info->getStartTime()
                && ('DJ' == $info->getArtistClone()->getType()
                    || 'Live' == $info->getArtistClone()->getType())
            ) {
                $bystage[$info->getStage()][] = $info;
            }
        }

        return $bystage;
    }

    public function getArtistInfosByType(string $type): array
    {
        $bystage = [];
        foreach ($this->eventArtistInfos as $info) {
            if (
                null !== $info->getStartTime()
                && $info->getArtistClone()->getType() == $type
            ) {
                $bystage[$info->getStage()][] = $info;
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

    public function setTicketPresaleStart(
        ?\DateTimeImmutable $ticketPresaleStart,
    ): self {
        $this->ticketPresaleStart = $ticketPresaleStart;

        return $this;
    }

    public function getTicketPresaleEnd(): ?\DateTimeImmutable
    {
        return $this->ticketPresaleEnd;
    }

    public function setTicketPresaleEnd(
        ?\DateTimeImmutable $ticketPresaleEnd,
    ): self {
        $this->ticketPresaleEnd = $ticketPresaleEnd;

        return $this;
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

    public function setShowNakkikoneLinkInEvent(
        ?bool $showNakkikoneLinkInEvent,
    ): self {
        $this->showNakkikoneLinkInEvent = $showNakkikoneLinkInEvent;

        return $this;
    }

    public function getRequireNakkiBookingsToBeDifferentTimes(): ?bool
    {
        return $this->requireNakkiBookingsToBeDifferentTimes;
    }

    public function setRequireNakkiBookingsToBeDifferentTimes(
        ?bool $requireNakkiBookingsToBeDifferentTimes,
    ): self {
        $this->requireNakkiBookingsToBeDifferentTimes = $requireNakkiBookingsToBeDifferentTimes;

        return $this;
    }

    public function responsibleMemberNakkis(Member $member): array
    {
        $bookings = [];
        foreach ($this->getNakkis() as $nakki) {
            if (
                $nakki->getResponsible() == $member
                || \in_array('ROLE_SUPER_ADMIN', $member->getUser()->getRoles())
                || $this->nakkiResponsibleAdmin->contains($member)
            ) {
                $bookings[
                    $nakki->getDefinition()->getName($member->getLocale())
                ]['b'][] = $nakki->getNakkiBookings();
                $bookings[
                    $nakki->getDefinition()->getName($member->getLocale())
                ]['mattermost'] = $nakki->getMattermostChannel();
                $bookings[
                    $nakki->getDefinition()->getName($member->getLocale())
                ]['responsible'] = $nakki->getResponsible();
            }
        }

        return $bookings;
    }

    public function memberNakkis(Member $member): array
    {
        $bookings = [];
        $booking = $member->getEventNakkiBooking($this);
        if ($booking instanceof NakkiBooking) {
            $nakki = $booking->getNakki();
            $bookings[$nakki->getDefinition()->getName($member->getLocale())][
                'b'
            ][] = $nakki->getNakkiBookings();
            $bookings[$nakki->getDefinition()->getName($member->getLocale())][
                'mattermost'
            ] = $nakki->getMattermostChannel();
            $bookings[$nakki->getDefinition()->getName($member->getLocale())][
                'responsible'
            ] = $nakki->getResponsible();
        }

        return $bookings;
    }

    public function getAllNakkiResponsibles(string $locale): array
    {
        $responsibles = [];
        foreach ($this->getNakkis() as $nakki) {
            $responsibles[$nakki->getDefinition()->getName($locale)][
                'mattermost'
            ] = $nakki->getMattermostChannel();
            $responsibles[$nakki->getDefinition()->getName($locale)][
                'responsible'
            ] = $nakki->getResponsible();
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
        // set the owning side to null (unless already changed)
        if (
            $this->emails->removeElement($email)
            && $email->getEvent() === $this
        ) {
            $email->setEvent(null);
        }

        return $this;
    }

    public function setRsvpOnlyToActiveMembers(
        ?bool $rsvpOnlyToActiveMembers,
    ): self {
        $this->rsvpOnlyToActiveMembers = $rsvpOnlyToActiveMembers;

        return $this;
    }

    public function setNakkiRequiredForTicketReservation(
        ?bool $nakkiRequiredForTicketReservation,
    ): self {
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

    public function setBackgroundEffectOpacity(
        ?int $backgroundEffectOpacity,
    ): self {
        $this->backgroundEffectOpacity = $backgroundEffectOpacity;

        return $this;
    }

    public function getBackgroundEffectPosition(): ?string
    {
        return $this->backgroundEffectPosition;
    }

    public function setBackgroundEffectPosition(
        ?string $backgroundEffectPosition,
    ): self {
        $this->backgroundEffectPosition = $backgroundEffectPosition;

        return $this;
    }

    public function getBackgroundEffectConfig(): ?string
    {
        return $this->backgroundEffectConfig;
    }

    public function setBackgroundEffectConfig(
        ?string $backgroundEffectConfig,
    ): self {
        $this->backgroundEffectConfig = $backgroundEffectConfig;

        return $this;
    }

    public function getArtistSignUpAskSetLength(): bool
    {
        return $this->artistSignUpAskSetLength;
    }

    public function setArtistSignUpAskSetLength(
        bool $artistSignUpAskSetLength,
    ): self {
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
        // set the owning side to null (unless already changed)
        if (
            $this->notifications->removeElement($notification)
            && $notification->getEvent() === $this
        ) {
            $notification->setEvent(null);
        }

        return $this;
    }

    public function getUrlByLang($lang): string
    {
        if ($this->externalUrl && $this->url) {
            return $this->url;
        }
        $year = '/'.$this->EventDate->format('Y');
        $url = '/'.$this->url;
        $event = '/en/event/';

        if ('fi' == $lang) {
            $event = '/tapahtuma/';
            $lang = '';
        }

        if (
            (null === $this->url || '' === $this->url || '0' === $this->url)
            && !$this->externalUrl
        ) {
            return 'https://entropy.fi'.$event.$this->id;
        }

        return $lang
            ? 'https://entropy.fi/'.$lang.$year.$url
            : 'https://entropy.fi'.$year.$url;
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
        // set the owning side to null (unless already changed)
        if (
            $this->happenings->removeElement($happening)
            && $happening->getEvent() === $this
        ) {
            $happening->setEvent(null);
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

    public function addNakkiResponsibleAdmin(
        Member $nakkiResponsibleAdmin,
    ): static {
        if (!$this->nakkiResponsibleAdmin->contains($nakkiResponsibleAdmin)) {
            $this->nakkiResponsibleAdmin->add($nakkiResponsibleAdmin);
        }

        return $this;
    }

    public function removeNakkiResponsibleAdmin(
        Member $nakkiResponsibleAdmin,
    ): static {
        $this->nakkiResponsibleAdmin->removeElement($nakkiResponsibleAdmin);

        return $this;
    }

    public function setAllowMembersToCreateHappenings(
        ?bool $allowMembersToCreateHappenings,
    ): static {
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

    public function getTemplate(): string
    {
        return $this->template ?: 'event.html.twig';
    }

    public function setTemplate(?string $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function getAbstractFi(): ?string
    {
        return $this->abstractFi;
    }

    public function setAbstractFi(?string $abstractFi): static
    {
        $this->abstractFi = $abstractFi;

        return $this;
    }

    public function getAbstractEn(): ?string
    {
        return $this->abstractEn;
    }

    public function setAbstractEn(?string $abstractEn): static
    {
        $this->abstractEn = $abstractEn;

        return $this;
    }

    public function getAbstract($lang): ?string
    {
        $func = 'abstract'.ucfirst((string) $lang);

        return $this->{$func};
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getTicketProducts(): Collection
    {
        return $this->products->filter(
            fn (Product $product): bool => $product->isTicket(),
        );
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setEvent($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        // set the owning side to null (unless already changed)
        if (
            $this->products->removeElement($product)
            && $product->getEvent() === $this
        ) {
            $product->setEvent(null);
        }

        return $this;
    }

    public function getTicketTypeCount($id): int
    {
        $return = 0;
        foreach ($this->getTickets() as $ticket) {
            if ($ticket->getStripeProductId() == $id) {
                ++$return;
            }
        }

        return $return;
    }

    public function getArtistSignUpInfoFi(): ?string
    {
        return $this->artistSignUpInfoFi;
    }

    public function setArtistSignUpInfoFi(?string $artistSignUpInfoFi): static
    {
        $this->artistSignUpInfoFi = $artistSignUpInfoFi;

        return $this;
    }

    public function getArtistSignUpInfoEn(): ?string
    {
        return $this->artistSignUpInfoEn;
    }

    public function setArtistSignUpInfoEn(?string $artistSignUpInfoEn): static
    {
        $this->artistSignUpInfoEn = $artistSignUpInfoEn;

        return $this;
    }

    public function getArtistSignUpInfo($lang): ?string
    {
        $func = 'artistSignUpInfo'.ucfirst((string) $lang);

        return $this->{$func};
    }

    public function setSendRsvpEmail(?bool $sendRsvpEmail): static
    {
        $this->sendRsvpEmail = $sendRsvpEmail;

        return $this;
    }

    public function getLinkToForums(): ?string
    {
        return $this->linkToForums;
    }

    public function setLinkToForums(?string $linkToForums): static
    {
        $this->linkToForums = $linkToForums;

        return $this;
    }

    public function getWikiPage(): ?string
    {
        return $this->wikiPage;
    }

    public function setWikiPage(?string $wikiPage): static
    {
        $this->wikiPage = $wikiPage;

        return $this;
    }

    public function isLocationPublic(): ?bool
    {
        return (bool) $this->location;
    }

    public function getTicketTotalAmount(): ?int
    {
        return $this->ticketTotalAmount;
    }

    public function setTicketTotalAmount(?int $ticketTotalAmount): static
    {
        $this->ticketTotalAmount = $ticketTotalAmount;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArtistDisplayConfig(): array
    {
        return $this->artistDisplayConfiguration->getConfig();
    }

    /**
     * @param array<string, mixed>|null $artistDisplayConfig
     */
    public function setArtistDisplayConfig(?array $artistDisplayConfig): self
    {
        $this->artistDisplayConfiguration->replace($artistDisplayConfig);

        return $this;
    }

    public function getArtistDisplayConfiguration(): ArtistDisplayConfiguration
    {
        return $this->artistDisplayConfiguration;
    }
}
