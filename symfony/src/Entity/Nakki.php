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
    private $id;

    #[ORM\ManyToOne(targetEntity: NakkiDefinition::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private $definition;

    #[ORM\Column(type: 'datetime_immutable')]
    private $startAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private $endAt;

    #[ORM\OneToMany(targetEntity: NakkiBooking::class, mappedBy: 'nakki', orphanRemoval: true)]
    private $nakkiBookings;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'nakkis')]
    #[ORM\JoinColumn(nullable: false)]
    private $event;

    #[ORM\Column(type: 'dateinterval')]
    private $nakkiInterval;

    #[ORM\ManyToOne(targetEntity: Member::class, inversedBy: 'responsibleForNakkis')]
    private $responsible;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $mattermostChannel;

    public function __construct()
    {
        $this->nakkiBookings = new ArrayCollection();
        $this->nakkiInterval = new \DateInterval('PT1H');
        ;
    }

    public function __toString(): string
    {
        return (string) $this->definition->getNameEn();
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

    public function getTimes()
    {
        $times = [];
        $diff = $this->getStartAt()->diff($this->getEndAt());
        $hours = $diff->h;
        $hours = ($hours + ($diff->days*24)) / $this->getNakkiInterval()->format('%h');
        for ($i = 0; $i < $hours; $i++) {
            $start = $i*$this->getNakkiInterval()->format('%h');
            $times[] = $this->getStartAt()->modify($start.' hour');
        }
        return $times;
    }

    public function getMemberByTime($date)
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
