<?php

namespace App\Entity;

use App\Repository\NakkiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NakkiRepository::class)]
class Nakki implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: NakkiDefinition::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private ?\App\Entity\NakkiDefinition $definition = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\OneToMany(targetEntity: NakkiBooking::class, mappedBy: 'nakki', orphanRemoval: true)]
    private $nakkiBookings;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'nakkis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private ?\App\Entity\Event $event = null;

    #[ORM\Column(type: 'dateinterval')]
    private \DateInterval $nakkiInterval;

    #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'responsibleForNakkis')]
    #[ORM\JoinColumn(onDelete: "SET NULL", nullable: true)]
    private ?\App\Entity\Member $responsible = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mattermostChannel = null;

    public function __construct()
    {
        $this->nakkiBookings = new ArrayCollection();
        $this->nakkiInterval = new \DateInterval('PT1H');
    }

    public function __toString(): string
    {
        return (string) $this->definition ? $this->definition->getNameEn() : 'N/A';
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDefinition(): ?NakkiDefinition
    {
        return $this->definition;
    }

    public function setDefinition(?NakkiDefinition $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;

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
            $nakkiBooking->setNakki($this);
        }

        return $this;
    }

    public function removeNakkiBooking(NakkiBooking $nakkiBooking): self
    {
        if ($this->nakkiBookings->removeElement($nakkiBooking)) {
            // set the owning side to null (unless already changed)
            if ($nakkiBooking->getNakki() === $this) {
                $nakkiBooking->setNakki(null);
            }
        }

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getNakkiInterval(): ?\DateInterval
    {
        return $this->nakkiInterval;
    }

    public function setNakkiInterval(\DateInterval $nakkiInterval): self
    {
        $this->nakkiInterval = $nakkiInterval;

        return $this;
    }

    public function getTimes(): array
    {
        $times = [];
        $diff = $this->getStartAt()->diff($this->getEndAt());
        $hours = $diff->h;
        $hours = ($hours + ($diff->days * 24)) / ((int) $this->getNakkiInterval()->format('%h'));
        for ($i = 0; $i < $hours; $i++) {
            $start = (int) $i * (int) $this->getNakkiInterval()->format('%h');
            $times[] = $this->getStartAt()->modify($start . ' hour');
        }
        return $times;
    }

    public function getMemberByTime($date): ?Member
    {
        foreach ($this->getNakkiBookings() as $booking) {
            if ($booking->getStartAt() == $date) {
                if ($booking->getMember()) {
                    return $booking->getMember();
                }
            }
        }
        return null;
    }

    public function getResponsible(): ?Member
    {
        return $this->responsible;
    }

    public function setResponsible(?Member $responsible): self
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getMattermostChannel(): ?string
    {
        return $this->mattermostChannel;
    }

    public function setMattermostChannel(?string $mattermostChannel): self
    {
        $this->mattermostChannel = $mattermostChannel;

        return $this;
    }
}
