<?php

namespace App\Entity;

use App\Entity\Sonata\SonataMediaMedia;
use App\Repository\HappeningRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HappeningRepository::class)]
class Happening
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nameFi = null;

    #[ORM\Column(length: 255)]
    private ?string $nameEn = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descriptionFi = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $descriptionEn = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $time = null;

    #[ORM\Column]
    private bool $needsPreliminarySignUp = false;

    #[ORM\Column]
    private bool $needsPreliminaryPayment = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paymentInfoFi = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paymentInfoEn = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\OneToMany(mappedBy: 'happening', targetEntity: HappeningBooking::class, orphanRemoval: true)]
    private Collection $bookings;

    #[ORM\ManyToMany(targetEntity: Member::class, inversedBy: 'happenings')]
    private Collection $owners;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?SonataMediaMedia $picture = null;

    #[ORM\ManyToOne(inversedBy: 'happenings')]
    private ?Event $event = null;

    #[ORM\Column]
    private ?int $maxSignUps = 0;

    #[ORM\Column(length: 255)]
    private ?string $slugFi = null;

    #[ORM\Column(length: 255)]
    private ?string $slugEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $priceFi = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $priceEn = null;

    #[ORM\Column]
    private ?bool $releaseThisHappeningInEvent = false;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->owners = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameFi(): ?string
    {
        return $this->nameFi;
    }

    public function setNameFi(string $nameFi): self
    {
        $this->nameFi = $nameFi;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(string $nameEn): self
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getDescriptionFi(): ?string
    {
        return $this->descriptionFi;
    }

    public function setDescriptionFi(string $descriptionFi): self
    {
        $this->descriptionFi = $descriptionFi;

        return $this;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(string $descriptionEn): self
    {
        $this->descriptionEn = $descriptionEn;

        return $this;
    }

    public function getTime(): ?\DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(\DateTimeInterface $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function isNeedsPreliminarySignUp(): ?bool
    {
        return $this->needsPreliminarySignUp;
    }

    public function setNeedsPreliminarySignUp(bool $needsPreliminarySignUp): self
    {
        $this->needsPreliminarySignUp = $needsPreliminarySignUp;

        return $this;
    }

    public function isNeedsPreliminaryPayment(): ?bool
    {
        return $this->needsPreliminaryPayment;
    }

    public function setNeedsPreliminaryPayment(bool $needsPreliminaryPayment): self
    {
        $this->needsPreliminaryPayment = $needsPreliminaryPayment;

        return $this;
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

    /**
     * @return Collection<int, HappeningBooking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(HappeningBooking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setHappening($this);
        }

        return $this;
    }

    public function removeBooking(HappeningBooking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getHappening() === $this) {
                $booking->setHappening(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Member>
     */
    public function getOwners(): Collection
    {
        return $this->owners;
    }

    public function addOwner(Member $owner): self
    {
        if (!$this->owners->contains($owner)) {
            $this->owners->add($owner);
        }

        return $this;
    }

    public function removeOwner(Member $owner): self
    {
        $this->owners->removeElement($owner);

        return $this;
    }

    public function getPicture(): ?SonataMediaMedia
    {
        return $this->picture;
    }

    public function setPicture(?SonataMediaMedia $picture): self
    {
        $this->picture = $picture;

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

    public function getMaxSignUps(): ?int
    {
        return $this->maxSignUps;
    }

    public function setMaxSignUps(int $maxSignUps): self
    {
        $this->maxSignUps = $maxSignUps;

        return $this;
    }

    public function getName($lang): ?string
    {
        $func = 'name' . ucfirst((string) $lang);
        return $this->{$func};
    }

    public function getSlug($lang): ?string
    {
        $func = 'slug' . ucfirst((string) $lang);
        return $this->{$func};
    }

    public function getDescription($lang): ?string
    {
        $func = 'description' . ucfirst((string) $lang);
        return $this->{$func};
    }

    public function getPaymentInfo($lang): ?string
    {
        $func = 'paymentInfo' . ucfirst((string) $lang);
        return $this->{$func};
    }

    public function getPrice($lang): ?string
    {
        $func = 'price' . ucfirst((string) $lang);
        return $this->{$func};
    }
    public function getSlugFi(): ?string
    {
        return $this->slugFi;
    }

    public function setSlugFi(string $slugFi): self
    {
        $this->slugFi = $slugFi;

        return $this;
    }

    public function getSlugEn(): ?string
    {
        return $this->slugEn;
    }

    public function setSlugEn(string $slugEn): self
    {
        $this->slugEn = $slugEn;

        return $this;
    }

    public function getPaymentInfoFi(): ?string
    {
        return $this->paymentInfoFi;
    }

    public function setPaymentInfoFi(?string $paymentInfoFi): self
    {
        $this->paymentInfoFi = $paymentInfoFi;

        return $this;
    }

    public function getPaymentInfoEn(): ?string
    {
        return $this->paymentInfoEn;
    }

    public function setPaymentInfoEn(?string $paymentInfoEn): self
    {
        $this->paymentInfoEn = $paymentInfoEn;

        return $this;
    }

    public function getPriceFi(): ?string
    {
        return $this->priceFi;
    }

    public function setPriceFi(?string $priceFi): self
    {
        $this->priceFi = $priceFi;

        return $this;
    }

    public function getPriceEn(): ?string
    {
        return $this->priceEn;
    }

    public function setPriceEn(?string $priceEn): self
    {
        $this->priceEn = $priceEn;

        return $this;
    }

    public function isReleaseThisHappeningInEvent(): ?bool
    {
        return $this->releaseThisHappeningInEvent;
    }

    public function setReleaseThisHappeningInEvent(bool $releaseThisHappeningInEvent): self
    {
        $this->releaseThisHappeningInEvent = $releaseThisHappeningInEvent;

        return $this;
    }
}
