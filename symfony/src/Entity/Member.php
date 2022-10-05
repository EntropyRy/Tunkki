<?php

namespace App\Entity;

use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use App\Entity\Ticket;

/**
 * Member
 */
#[ORM\Table(name: 'member')]
#[ORM\Entity(repositoryClass: \App\Repository\MemberRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
class Member implements \Stringable
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private readonly int $id;

    #[ORM\Column(name: 'firstname', type: 'string', length: 190)]
    private string $firstname;

    #[ORM\Column(name: 'lastname', type: 'string', length: 190)]
    private string $lastname;

    #[ORM\Column(name: 'email', type: 'string', length: 190, unique: true)]
    private $email;

    #[ORM\Column(name: 'username', type: 'string', length: 190, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(name: 'phone', type: 'string', length: 190, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'CityOfResidence', type: 'string', length: 190, nullable: true)]
    private ?string $CityOfResidence = null;

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(name: 'createdAt', type: 'datetime')]
    private \DateTime $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(name: 'updatedAt', type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(name: 'isActiveMember', type: 'boolean')]
    private bool $isActiveMember = false;

    #[ORM\Column(name: 'rejectReasonSent', type: 'boolean')]
    private bool $rejectReasonSent = false;

    #[ORM\Column(name: 'StudentUnionMember', type: 'boolean')]
    private bool $StudentUnionMember = false;

    #[ORM\Column(name: 'Application', type: 'text', nullable: true)]
    private ?string $Application = null;

    #[ORM\Column(name: 'reject_reason', type: 'text', nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(name: 'ApplicationDate', type: 'datetime', nullable: true)]
    private ?\DateTime $ApplicationDate;

    #[ORM\Column(name: 'ApplicationHandledDate', type: 'datetime', nullable: true)]
    private ?\DateTime $ApplicationHandledDate;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $AcceptedAsHonoraryMember;

    #[ORM\Column(type: 'boolean')]
    private bool $isFullMember = false;

    #[ORM\OneToOne(targetEntity: User::class, mappedBy: 'member', cascade: ['persist', 'remove'])]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
    private $user;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $locale = 'fi';

    #[ORM\OneToMany(targetEntity: Artist::class, mappedBy: 'member', orphanRemoval: true)]
    #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
    private $artist;

    #[ORM\OneToMany(targetEntity: DoorLog::class, mappedBy: 'member', orphanRemoval: true)]
    private $doorLogs;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $theme;

    #[ORM\OneToMany(targetEntity: RSVP::class, mappedBy: 'member', orphanRemoval: true)]
    private $RSVPs;

    #[ORM\OneToMany(targetEntity: NakkiBooking::class, mappedBy: 'member')]
    private $nakkiBookings;

    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'owner')]
    private $tickets;

    #[ORM\OneToMany(targetEntity: Nakki::class, mappedBy: 'responsible')]
    private $responsibleForNakkis;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $denyKerdeAccess = false;

    public function __construct()
    {
        $this->artist = new ArrayCollection();
        $this->doorLogs = new ArrayCollection();
        $this->RSVPs = new ArrayCollection();
        $this->nakkiBookings = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->responsibleForNakkis = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->firstname.' '.$this->lastname;
    }

    /**
     * Set email.
     *
     * @param string $email
     *
     * @return Member
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set phone.
     *
     * @param string|null $phone
     *
     * @return Member
     */
    public function setPhone($phone = null)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone.
     *
     * @return string|null
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Member
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt.
     *
     * @param \DateTime $updatedAt
     *
     * @return Member
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
    /**
     * Set studentUnionMember.
     *
     * @param bool|null $studentUnionMember
     *
     * @return Member
     */
    public function setStudentUnionMember($studentUnionMember = null)
    {
        $this->StudentUnionMember = $studentUnionMember;

        return $this;
    }

    /**
     * Get studentUnionMember.
     *
     * @return bool|null
     */
    public function getStudentUnionMember()
    {
        return $this->StudentUnionMember;
    }

    /**
     * Set application.
     *
     * @param string|null $application
     *
     * @return Member
     */
    public function setApplication($application = null)
    {
        $this->Application = $application;

        return $this;
    }

    /**
     * Get application.
     *
     * @return string|null
     */
    public function getApplication()
    {
        return $this->Application;
    }

    /**
     * Set applicationDate.
     *
     * @param \DateTime|null $applicationDate
     *
     * @return Member
     */
    public function setApplicationDate($applicationDate = null)
    {
        $this->ApplicationDate = $applicationDate;

        return $this;
    }

    /**
     * Get applicationDate.
     *
     * @return \DateTime|null
     */
    public function getApplicationDate()
    {
        return $this->ApplicationDate;
    }

    /**
     * Set cityOfResidence.
     *
     * @param string|null $cityOfResidence
     *
     * @return Member
     */
    public function setCityOfResidence($cityOfResidence = null)
    {
        $this->CityOfResidence = $cityOfResidence;

        return $this;
    }

    /**
     * Get cityOfResidence.
     *
     * @return string|null
     */
    public function getCityOfResidence()
    {
        return $this->CityOfResidence;
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Set firstname.
     *
     * @param string $firstname
     *
     * @return Member
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set lastname.
     *
     * @param string $lastname
     *
     * @return Member
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname.
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set isActiveMember.
     *
     * @param bool $isActiveMember
     *
     * @return Member
     */
    public function setIsActiveMember($isActiveMember)
    {
        $this->isActiveMember = $isActiveMember;

        return $this;
    }

    /**
     * Get isActiveMember.
     *
     * @return bool
     */
    public function getIsActiveMember()
    {
        return $this->isActiveMember;
    }

    /**
     * Set rejectReason.
     *
     * @param string|null $rejectReason
     *
     * @return Member
     */
    public function setRejectReason($rejectReason = null)
    {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    /**
     * Get rejectReason.
     *
     * @return string|null
     */
    public function getRejectReason()
    {
        return $this->rejectReason;
    }

    /**
     * Set rejectReasonSent.
     *
     * @param bool $rejectReasonSent
     *
     * @return Member
     */
    public function setRejectReasonSent($rejectReasonSent)
    {
        $this->rejectReasonSent = $rejectReasonSent;

        return $this;
    }

    /**
     * Get rejectReasonSent.
     *
     * @return bool
     */
    public function getRejectReasonSent()
    {
        return $this->rejectReasonSent;
    }


    /**
     * Set applicationHandledDate.
     *
     * @param \DateTime|null $applicationHandledDate
     *
     * @return Member
     */
    public function setApplicationHandledDate($applicationHandledDate = null)
    {
        $this->ApplicationHandledDate = $applicationHandledDate;

        return $this;
    }

    /**
     * Get applicationHandledDate.
     *
     * @return \DateTime|null
     */
    public function getApplicationHandledDate()
    {
        return $this->ApplicationHandledDate;
    }

    /**
     * Set username.
     *
     * @param string|null $username
     *
     * @return Member
     */
    public function setUsername($username = null)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username.
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->username;
    }

    public function getAcceptedAsHonoraryMember(): ?\DateTimeInterface
    {
        return $this->AcceptedAsHonoraryMember;
    }

    public function setAcceptedAsHonoraryMember(?\DateTimeInterface $AcceptedAsHonoraryMember): self
    {
        $this->AcceptedAsHonoraryMember = $AcceptedAsHonoraryMember;

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

        // set (or unset) the owning side of the relation if necessary
        $newMember = null === $user ? null : $this;
        if ($user->getMember() !== $newMember) {
            $user->setMember($newMember);
        }

        return $this;
    }
    public function getProgressP(): string
    {
        if ($this->AcceptedAsHonoraryMember) {
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
        if ($this->isFullMember) {
            return true;
        }
        return false;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
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
            // set the owning side to null (unless already changed)
            if ($artist->getMember() === $this) {
                $artist->setMember(null);
            }
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
        if ($this->doorLogs->removeElement($doorLog)) {
            // set the owning side to null (unless already changed)
            if ($doorLog->getMember() === $this) {
                $doorLog->setMember(null);
            }
        }

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
        if ($this->RSVPs->removeElement($rSVP)) {
            // set the owning side to null (unless already changed)
            if ($rSVP->getMember() === $this) {
                $rSVP->setMember(null);
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
            $nakkiBooking->setMember($this);
        }

        return $this;
    }

    public function removeNakkiBooking(NakkiBooking $nakkiBooking): self
    {
        if ($this->nakkiBookings->removeElement($nakkiBooking)) {
            // set the owning side to null (unless already changed)
            if ($nakkiBooking->getMember() === $this) {
                $nakkiBooking->setMember(null);
            }
        }

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
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getOwner() === $this) {
                $ticket->setOwner(null);
            }
        }

        return $this;
    }

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
        if ($this->responsibleForNakkis->removeElement($responsibleForNakki)) {
            // set the owning side to null (unless already changed)
            if ($responsibleForNakki->getResponsible() === $this) {
                $responsibleForNakki->setResponsible(null);
            }
        }

        return $this;
    }

    public function getEventNakkiBooking($event)
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
}
