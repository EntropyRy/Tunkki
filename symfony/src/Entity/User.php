<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'member')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, \Stringable, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(min: 8)]
    private string $password = '';

    #[Assert\Length(min: 8)]
    private ?string $plainPassword = null;

    #[
        ORM\OneToOne(
            targetEntity: Member::class,
            inversedBy: 'user',
            cascade: ['persist', 'remove'],
        ),
    ]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private Member $member;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $CreatedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $UpdatedAt;

    /**
     * @var Collection<int, Reward>
     */
    #[
        ORM\OneToMany(
            targetEntity: Reward::class,
            mappedBy: 'user',
            orphanRemoval: true,
        ),
    ]
    private Collection $rewards;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $LastLogin = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $MattermostId = null;

    /**
     * @var Collection<int, AccessGroups>
     */
    #[ORM\ManyToMany(targetEntity: AccessGroups::class, mappedBy: 'users')]
    private Collection $accessGroups;

    #[ORM\Column(length: 255)]
    private string $authId = '';

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->CreatedAt = $now;
        $this->UpdatedAt = $now;
        $this->rewards = new ArrayCollection();
        $this->accessGroups = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTimeImmutable();
        $this->CreatedAt = $now;
        $this->UpdatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->UpdatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->member->getEmail();
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        if ($this->member instanceof Member && $this->member->getUsername()) {
            return $this->member->getUsername();
        }

        return 'N/A';
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        if ($this->member instanceof Member && $this->member->getEmail()) {
            return $this->member->getEmail();
        }

        return 'N/A';
    }

    /**
     * @see UserInterface
     */
    #[\Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';
        foreach ($this->accessGroups as $group) {
            if ($group->getActive()) {
                foreach ($group->getRoles() as $role) {
                    $roles[] = $role;
                }
            }
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    #[\Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    #[\Override]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->CreatedAt;
    }

    public function setCreatedAt(\DateTimeImmutable $CreatedAt): self
    {
        $this->CreatedAt = $CreatedAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->UpdatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $UpdatedAt): self
    {
        $this->UpdatedAt = $UpdatedAt;

        return $this;
    }

    public function getLastLogin(): ?\DateTimeImmutable
    {
        return $this->LastLogin;
    }

    public function setLastLogin(?\DateTimeImmutable $LastLogin): self
    {
        $this->LastLogin = $LastLogin;

        return $this;
    }

    /**
     * @return Collection<int, Reward>
     */
    public function getRewards(): Collection
    {
        return $this->rewards;
    }

    public function addReward(Reward $reward): self
    {
        if (!$this->rewards->contains($reward)) {
            $this->rewards[] = $reward;
            $reward->setUser($this);
        }

        return $this;
    }

    public function removeReward(Reward $reward): self
    {
        if ($this->rewards->contains($reward)) {
            $this->rewards->removeElement($reward);
            // set the owning side to null (unless already changed)
            if ($reward->getUser() === $this) {
                $reward->setUser(null);
            }
        }

        return $this;
    }

    public function getMattermostId(): ?string
    {
        return $this->MattermostId;
    }

    public function setMattermostId(?string $MattermostId): self
    {
        $this->MattermostId = $MattermostId;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void
    {
        $this->plainPassword = $plainPassword;
        $this->password = '';
    }

    /**
     * @return Collection<int, AccessGroups>
     */
    public function getAccessGroups(): Collection
    {
        return $this->accessGroups;
    }

    public function addAccessGroup(AccessGroups $accessGroup): self
    {
        if (!$this->accessGroups->contains($accessGroup)) {
            $this->accessGroups[] = $accessGroup;
            $accessGroup->addUser($this);
        }

        return $this;
    }

    public function removeAccessGroup(AccessGroups $accessGroup): self
    {
        if ($this->accessGroups->removeElement($accessGroup)) {
            $accessGroup->removeUser($this);
        }

        return $this;
    }

    #[\Override]
    public function __toString(): string
    {
        if ($this->member instanceof Member) {
            return (string) $this->member->getName();
        } else {
            return 'user: '.$this->id;
        }
    }

    public function getLocale(): string
    {
        return $this->member->getLocale();
    }

    public function getAuthId(): string
    {
        return $this->authId;
    }

    public function setAuthId(string $authId): static
    {
        $this->authId = $authId;

        return $this;
    }
}
