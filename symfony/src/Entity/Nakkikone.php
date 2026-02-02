<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NakkikoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Nakkikone - Volunteer work management aggregate root.
 *
 * Manages all volunteer-related concerns for an event:
 * - Configuration (enabled, info texts, rules)
 * - Nakki slots (available volunteer positions)
 * - NakkiBookings (member sign-ups)
 * - Responsible admins
 */
#[ORM\Entity(repositoryClass: NakkikoneRepository::class)]
class Nakkikone implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // === Configuration Fields (inline, not embeddable) ===

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $infoFi = 'Valitse vähintään 2 tunnin Nakkia sekä purku tai roudaus';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $infoEn = 'Choose at least two (2) Nakkis that are 1 hour length and build up or take down';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $showLinkInEvent = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $requireDifferentTimes = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $requiredForTicketReservation = false;

    // === Collections ===

    /**
     * @var Collection<int, Nakki>
     */
    #[ORM\OneToMany(
        targetEntity: Nakki::class,
        mappedBy: 'nakkikone',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $nakkis;

    /**
     * @var Collection<int, NakkiBooking>
     */
    #[ORM\OneToMany(
        targetEntity: NakkiBooking::class,
        mappedBy: 'nakkikone',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $bookings;

    /**
     * @var Collection<int, Member>
     */
    #[ORM\ManyToMany(targetEntity: Member::class)]
    #[ORM\JoinTable(name: 'nakkikone_responsible_admins')]
    private Collection $responsibleAdmins;

    // === Constructor ===

    public function __construct(#[ORM\OneToOne(targetEntity: Event::class, inversedBy: 'nakkikone')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Event $event)
    {
        $this->initCollections();
    }

    // === Core Accessors ===

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    // === Configuration Accessors ===

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getInfoFi(): ?string
    {
        return $this->infoFi;
    }

    public function setInfoFi(?string $infoFi): self
    {
        $this->infoFi = $infoFi;

        return $this;
    }

    public function getInfoEn(): ?string
    {
        return $this->infoEn;
    }

    public function setInfoEn(?string $infoEn): self
    {
        $this->infoEn = $infoEn;

        return $this;
    }

    public function getInfoByLocale(string $locale): ?string
    {
        return 'en' === $locale ? $this->infoEn : $this->infoFi;
    }

    public function isShowLinkInEvent(): bool
    {
        return $this->showLinkInEvent;
    }

    public function shouldShowLinkInEvent(): bool
    {
        return $this->showLinkInEvent;
    }

    public function setShowLinkInEvent(bool $showLinkInEvent): self
    {
        $this->showLinkInEvent = $showLinkInEvent;

        return $this;
    }

    public function isRequireDifferentTimes(): bool
    {
        return $this->requireDifferentTimes;
    }

    public function requiresDifferentTimes(): bool
    {
        return $this->requireDifferentTimes;
    }

    public function setRequireDifferentTimes(bool $requireDifferentTimes): self
    {
        $this->requireDifferentTimes = $requireDifferentTimes;

        return $this;
    }

    public function isRequiredForTicketReservation(): bool
    {
        return $this->requiredForTicketReservation;
    }

    public function setRequiredForTicketReservation(bool $requiredForTicketReservation): self
    {
        $this->requiredForTicketReservation = $requiredForTicketReservation;

        return $this;
    }

    public function __toString(): string
    {
        $date = $this->event->getEventDate()->format('Y-m-d');

        return 'Nakkikone for '.$this->event.' ('.$date.')';
    }

    // === Nakki Collection Management ===

    /**
     * @return Collection<int, Nakki>
     */
    public function getNakkis(): Collection
    {
        $this->initCollections();

        return $this->nakkis;
    }

    public function addNakki(Nakki $nakki): self
    {
        if (!$this->nakkis->contains($nakki)) {
            $this->nakkis->add($nakki);
            $nakki->setNakkikone($this);
        }

        return $this;
    }

    public function removeNakki(Nakki $nakki): self
    {
        if ($this->nakkis->removeElement($nakki)) {
            // Nakki is the owning side; avoid nulling here to prevent invalid state.
        }

        return $this;
    }

    // === NakkiBooking Collection Management ===

    /**
     * @return Collection<int, NakkiBooking>
     */
    public function getBookings(): Collection
    {
        $this->initCollections();

        return $this->bookings;
    }

    public function addBooking(NakkiBooking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setNakkikone($this);
        }

        return $this;
    }

    public function removeBooking(NakkiBooking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            // NakkiBooking is the owning side; avoid nulling here to prevent invalid state.
        }

        return $this;
    }

    // === Responsible Admins Management ===

    /**
     * @return Collection<int, Member>
     */
    public function getResponsibleAdmins(): Collection
    {
        $this->initCollections();

        return $this->responsibleAdmins;
    }

    private function initCollections(): void
    {
        if (!isset($this->nakkis)) {
            $this->nakkis = new ArrayCollection();
        }
        if (!isset($this->bookings)) {
            $this->bookings = new ArrayCollection();
        }
        if (!isset($this->responsibleAdmins)) {
            $this->responsibleAdmins = new ArrayCollection();
        }
    }

    public function addResponsibleAdmin(Member $admin): self
    {
        if (!$this->responsibleAdmins->contains($admin)) {
            $this->responsibleAdmins->add($admin);
        }

        return $this;
    }

    public function removeResponsibleAdmin(Member $admin): self
    {
        $this->responsibleAdmins->removeElement($admin);

        return $this;
    }

    // === Business Logic (moved from Event) ===

    /**
     * Get all Nakkis visible to a responsible member (admin view).
     *
     * @return array<string, array{b: array, mattermost: ?string, responsible: ?Member}>
     */
    public function getResponsibleMemberNakkis(Member $member): array
    {
        $result = [];

        foreach ($this->nakkis as $nakki) {
            $isResponsible = $nakki->getResponsible() === $member;
            $isAdmin = $this->responsibleAdmins->contains($member);

            if ($isResponsible || $isAdmin) {
                $locale = $member->getLocale();
                $name = $nakki->getDefinition()->getName($locale);

                $result[$name]['b'][] = $nakki->getNakkiBookings();
                $result[$name]['mattermost'] = $nakki->getMattermostChannel();
                $result[$name]['responsible'] = $nakki->getResponsible();
            }
        }

        return $result;
    }

    /**
     * Get Nakkis that a member has booked (member view).
     *
     * @return array<string, array{b: array, mattermost: ?string, responsible: ?Member}>
     */
    public function getMemberNakkis(Member $member): array
    {
        $result = [];

        // Find booking for this member+event
        $booking = $member->getEventNakkiBooking($this->event);

        if ($booking instanceof NakkiBooking) {
            $nakki = $booking->getNakki();
            $locale = $member->getLocale();
            $name = $nakki->getDefinition()->getName($locale);

            $result[$name]['b'][] = $nakki->getNakkiBookings();
            $result[$name]['mattermost'] = $nakki->getMattermostChannel();
            $result[$name]['responsible'] = $nakki->getResponsible();
        }

        return $result;
    }

    /**
     * Get all responsible members for all Nakkis (reporting).
     *
     * @return array<string, array{mattermost: ?string, responsible: ?Member}>
     */
    public function getAllResponsibles(string $locale): array
    {
        $result = [];

        foreach ($this->nakkis as $nakki) {
            $name = $nakki->getDefinition()->getName($locale);
            $result[$name]['mattermost'] = $nakki->getMattermostChannel();
            $result[$name]['responsible'] = $nakki->getResponsible();
        }

        return $result;
    }

    /**
     * Check if a member has a Nakki booking (for ticket reservation validation).
     */
    public function ticketHolderHasBooking(?Member $member): ?NakkiBooking
    {
        if (!$this->requiredForTicketReservation) {
            return null;
        }

        if (!$member instanceof Member) {
            return null;
        }

        foreach ($this->bookings as $booking) {
            if ($booking->getMember() === $member) {
                return $booking;
            }
        }

        return null;
    }

    /**
     * Check if a member has a nakki booking (only if nakki is required for ticket reservation).
     */
    public function ticketHolderHasNakki(Member $member): ?NakkiBooking
    {
        if (!$this->requiredForTicketReservation) {
            return null;
        }

        return $this->getMemberBooking($member);
    }

    private function getMemberBooking(Member $member): ?NakkiBooking
    {
        foreach ($this->bookings as $booking) {
            if ($booking->getMember() === $member) {
                return $booking;
            }
        }

        return null;
    }
}
