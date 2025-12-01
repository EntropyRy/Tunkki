<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Member.
 */
#[ORM\Table(name: 'member')]
#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'email.unique')]
class Member implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'firstname', type: Types::STRING, length: 190)]
    private string $firstname = '';

    #[ORM\Column(name: 'lastname', type: Types::STRING, length: 190)]
    private string $lastname = '';

    #[Assert\NotBlank(message: 'email.required')]
    #[Assert\Email(message: 'email.invalid')]
    #[ORM\Column(name: 'email', type: Types::STRING, length: 190, unique: true)]
    private $email;

    #[
        ORM\Column(
            name: 'username',
            type: Types::STRING,
            length: 190,
            nullable: true,
        ),
    ]
    #[Assert\Length(max: 190, maxMessage: 'username.max_length')]
    #[Assert\NotBlank(message: 'username.required', allowNull: true)]
    private ?string $username = null;

    #[
        ORM\Column(
            name: 'phone',
            type: Types::STRING,
            length: 190,
            nullable: true,
        ),
    ]
    private ?string $phone = null;

    #[
        ORM\Column(
            name: 'CityOfResidence',
            type: Types::STRING,
            length: 190,
            nullable: true,
        ),
    ]
    private ?string $CityOfResidence = null;

    #[ORM\Column(name: 'createdAt', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updatedAt', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'isActiveMember', type: Types::BOOLEAN)]
    private bool $isActiveMember = false;

    #[ORM\Column(name: 'rejectReasonSent', type: Types::BOOLEAN)]
    private bool $rejectReasonSent = false;

    #[ORM\Column(name: 'StudentUnionMember', type: Types::BOOLEAN)]
    private bool $StudentUnionMember = false;

    #[ORM\Column(name: 'Application', type: 'text', nullable: true)]
    private ?string $Application = null;

    #[ORM\Column(name: 'reject_reason', type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(name: 'ApplicationDate', type: 'datetime', nullable: true)]
    private ?\DateTime $ApplicationDate = null;

    #[
        ORM\Column(
            name: 'ApplicationHandledDate',
            type: 'datetime',
            nullable: true,
        ),
    ]
    private ?\DateTime $ApplicationHandledDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $AcceptedAsHonoraryMember = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isFullMember = false;

    #[
        ORM\OneToOne(
            targetEntity: User::class,
            mappedBy: 'member',
            cascade: ['persist', 'remove'],
        ),
    ]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 8)]
    private string $locale = 'fi';

    /**
     * @var Collection<int, Artist>
     */
    #[
        ORM\OneToMany(
            targetEntity: Artist::class,
            mappedBy: 'member',
            orphanRemoval: true,
        ),
    ]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
    private $artist;

    /**
     * @var Collection<int, DoorLog>
     */
    #[
        ORM\OneToMany(
            targetEntity: DoorLog::class,
            mappedBy: 'member',
            orphanRemoval: true,
        ),
    ]
    private $doorLogs;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $theme = null;

    /**
     * @var Collection<int, RSVP>
     */
    #[
        ORM\OneToMany(
            targetEntity: RSVP::class,
            mappedBy: 'member',
            orphanRemoval: true,
        ),
    ]
    private $RSVPs;

    /**
     * @var Collection<int, NakkiBooking>
     */
    #[ORM\OneToMany(targetEntity: NakkiBooking::class, mappedBy: 'member')]
    private $nakkiBookings;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'owner')]
    private $tickets;

    /**
     * @var Collection<int, Nakki>
     */
    #[ORM\OneToMany(targetEntity: Nakki::class, mappedBy: 'responsible')]
    private $responsibleForNakkis;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $denyKerdeAccess = false;

    /**
     * @var Collection<int, HappeningBooking>
     */
    #[
        ORM\OneToMany(
            targetEntity: HappeningBooking::class,
            mappedBy: 'member',
            cascade: ['persist', 'remove'],
        ),
    ]
    private Collection $happeningBooking;

    /**
     * @var Collection<int, Happening>
     */
    #[ORM\ManyToMany(targetEntity: Happening::class, mappedBy: 'owners')]
    private Collection $happenings;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\Column]
    private bool $emailVerified = false;

    #[ORM\Column]
    private bool $allowInfoMails = true;

    #[ORM\Column]
    private bool $allowActiveMemberMails = true;

    #[ORM\Column(type: Types::STRING, length: 190, nullable: true)]
    private ?string $epicsUsername = null;

    public function __construct()
    {
        $this->artist = new ArrayCollection();
        $this->doorLogs = new ArrayCollection();
        $this->RSVPs = new ArrayCollection();
        $this->nakkiBookings = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->responsibleForNakkis = new ArrayCollection();
        $this->happenings = new ArrayCollection();
        $this->happeningBooking = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->firstname.' '.$this->lastname;
    }

    public function setEmail(?string $email): self
    {
        // If the email is actually changing (non-null to a different non-null value)
        // and the current email was verified, force re-verification.
        if (
            null !== $this->email
            && null !== $email
            && $this->email !== $email
            && $this->emailVerified
        ) {
            $this->emailVerified = false;
        }

        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setPhone(?string $phone = null): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $UpdatedAt): self
    {
        $this->updatedAt = $UpdatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setStudentUnionMember(
        mixed $studentUnionMember = null,
    ): self {
        $this->StudentUnionMember = $studentUnionMember;

        return $this;
    }

    public function getStudentUnionMember(): ?bool
    {
        return $this->StudentUnionMember;
    }

    public function setApplication(mixed $application = null): self
    {
        $this->Application = $application;

        return $this;
    }

    public function getApplication(): ?string
    {
        return $this->Application;
    }

    public function setApplicationDate(mixed $applicationDate = null): self
    {
        $this->ApplicationDate = $applicationDate;

        return $this;
    }

    public function getApplicationDate(): ?\DateTime
    {
        return $this->ApplicationDate;
    }

    public function setCityOfResidence(mixed $cityOfResidence = null): self
    {
        $this->CityOfResidence = $cityOfResidence;

        return $this;
    }

    public function getCityOfResidence(): ?string
    {
        return $this->CityOfResidence;
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function setFirstname(mixed $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setLastname(mixed $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setIsActiveMember(mixed $isActiveMember): self
    {
        $this->isActiveMember = $isActiveMember;

        return $this;
    }

    public function getIsActiveMember(): bool
    {
        return $this->isActiveMember;
    }

    public function setRejectReason(mixed $rejectReason = null): self
    {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }

    public function setRejectReasonSent(mixed $rejectReasonSent): self
    {
        $this->rejectReasonSent = $rejectReasonSent;

        return $this;
    }

    public function getRejectReasonSent(): ?bool
    {
        return $this->rejectReasonSent;
    }

    public function setApplicationHandledDate(
        mixed $applicationHandledDate = null,
    ): self {
        $this->ApplicationHandledDate = $applicationHandledDate;

        return $this;
    }

    public function getApplicationHandledDate(): ?\DateTime
    {
        return $this->ApplicationHandledDate;
    }

    public function setUsername(mixed $username = null): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getAcceptedAsHonoraryMember(): ?\DateTimeImmutable
    {
        return $this->AcceptedAsHonoraryMember;
    }

    public function setAcceptedAsHonoraryMember(
        ?\DateTimeInterface $AcceptedAsHonoraryMember,
    ): self {
        $this->AcceptedAsHonoraryMember = $AcceptedAsHonoraryMember instanceof \DateTimeImmutable
            ? $AcceptedAsHonoraryMember
            : ($AcceptedAsHonoraryMember instanceof \DateTime
                ? \DateTimeImmutable::createFromInterface($AcceptedAsHonoraryMember)
                : null);

        return $this;
    }

    public function getIsFullMember(): ?bool
    {
        return $this->isFullMember;
    }

    public function setIsFullMember(bool $isFullMember): self
    {
        $this->isFullMember = $isFullMember;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        // Guard: if null user provided, just detach existing relation (owning side cleared above)
        if (!$user instanceof User) {
            return $this;
        }

        // set (or unset) the owning side of the relation if necessary
        $newMember = $this;
        if ($user->getMember() !== $newMember) {
            $user->setMember($newMember);
        }

        return $this;
    }

    public function getProgressP(): string
    {
        if ($this->AcceptedAsHonoraryMember instanceof \DateTimeInterface) {
            return '100';
        }
        if ($this->isActiveMember) {
            return '66';
        } else {
            return '33';
        }
    }

    public function canVote(): bool
    {
        if ($this->StudentUnionMember) {
            return true;
        }

        return $this->isFullMember;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return Collection|Artist[]
     */
    public function getArtist(): Collection
    {
        return $this->artist;
    }

    /**
     * @param int $id
     */
    public function getArtistWithId($id): ?Artist
    {
        foreach ($this->getArtist() as $artist) {
            if ($artist->getId() == $id) {
                return $artist;
            }
        }

        return null;
    }

    public function addArtist(Artist $artist): self
    {
        if (!$this->artist->contains($artist)) {
            $this->artist[] = $artist;
            $artist->setMember($this);
        }

        return $this;
    }

    public function removeArtist(Artist $artist): self
    {
        if ($this->artist->contains($artist)) {
            $this->artist->removeElement($artist);
        }

        return $this;
    }

    /**
     * @return Collection|DoorLog[]
     */
    public function getDoorLogs(): Collection
    {
        return $this->doorLogs;
    }

    public function addDoorLog(DoorLog $doorLog): self
    {
        if (!$this->doorLogs->contains($doorLog)) {
            $this->doorLogs[] = $doorLog;
            $doorLog->setMember($this);
        }

        return $this;
    }

    public function removeDoorLog(DoorLog $doorLog): self
    {
        $this->doorLogs->removeElement($doorLog);

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

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
            $rSVP->setMember($this);
        }

        return $this;
    }

    public function removeRSVP(RSVP $rSVP): self
    {
        $this->RSVPs->removeElement($rSVP);

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
            $nakkiBooking->setMember($this);
        }

        return $this;
    }

    public function removeNakkiBooking(NakkiBooking $nakkiBooking): self
    {
        $this->nakkiBookings->removeElement($nakkiBooking);

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
            $ticket->setOwner($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): self
    {
        $this->tickets->removeElement($ticket);

        return $this;
    }

    /**
     * @param Event $event
     */
    public function getTicketForEvent($event): ?Ticket
    {
        foreach ($this->tickets as $ticket) {
            if ($ticket->getEvent() == $event) {
                return $ticket;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Nakki>
     */
    public function getResponsibleForNakkis(): Collection
    {
        return $this->responsibleForNakkis;
    }

    public function addResponsibleForNakki(Nakki $responsibleForNakki): self
    {
        if (!$this->responsibleForNakkis->contains($responsibleForNakki)) {
            $this->responsibleForNakkis[] = $responsibleForNakki;
            $responsibleForNakki->setResponsible($this);
        }

        return $this;
    }

    public function removeResponsibleForNakki(Nakki $responsibleForNakki): self
    {
        $this->responsibleForNakkis->removeElement($responsibleForNakki);

        return $this;
    }

    /**
     * @param Event $event
     */
    public function getEventNakkiBooking($event): ?NakkiBooking
    {
        $bookings = $this->getNakkiBookings();
        foreach ($bookings as $b) {
            if ($b->getEvent() == $event) {
                return $b;
            }
        }

        return null;
    }

    public function getDenyKerdeAccess(): ?bool
    {
        return $this->denyKerdeAccess;
    }

    public function setDenyKerdeAccess(?bool $denyKerdeAccess): self
    {
        $this->denyKerdeAccess = $denyKerdeAccess;

        return $this;
    }

    public function getHappeningBooking(): ?Collection
    {
        return $this->happeningBooking;
    }

    public function addHappeningBooking(
        HappeningBooking $happeningBooking,
    ): self {
        if (!$this->happeningBooking->contains($happeningBooking)) {
            $this->happeningBooking->add($happeningBooking);
            $happeningBooking->setMember($this);
        }

        return $this;
    }

    public function removeHappeningBooking(
        HappeningBooking $happeningBooking,
    ): self {
        if ($this->happeningBooking->removeElement($happeningBooking)) {
            $happeningBooking->setMember();
        }

        return $this;
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
            $happening->addOwner($this);
        }

        return $this;
    }

    public function removeHappening(Happening $happening): self
    {
        if ($this->happenings->removeElement($happening)) {
            $happening->removeOwner($this);
        }

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;

        return $this;
    }

    public function isAllowInfoMails(): bool
    {
        return $this->allowInfoMails;
    }

    public function setAllowInfoMails(bool $allowInfoMails): static
    {
        $this->allowInfoMails = $allowInfoMails;

        return $this;
    }

    public function isAllowActiveMemberMails(): bool
    {
        return $this->allowActiveMemberMails;
    }

    public function setAllowActiveMemberMails(
        bool $allowActiveMemberMails,
    ): static {
        $this->allowActiveMemberMails = $allowActiveMemberMails;

        return $this;
    }

    public function getEpicsUsername(): ?string
    {
        return $this->epicsUsername;
    }

    public function setEpicsUsername(?string $epicsUsername): static
    {
        $this->epicsUsername = $epicsUsername;

        return $this;
    }

    public function getStreamArtists(): array
    {
        $streamArtists = [];
        foreach ($this->getArtist() as $artist) {
            if ('ART' != $artist->getType()) {
                $streamArtists[] = $artist;
            }
        }

        return $streamArtists;
    }
}
