<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
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
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(min: 8)]
    private ?string $password = null;

    #[Assert\Length(min: 8)]
    private $plainPassword;

    #[ORM\OneToOne(targetEntity: Member::class, inversedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Member $member = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $CreatedAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $UpdatedAt = null;

    #[ORM\OneToMany(targetEntity: Reward::class, mappedBy: 'user', orphanRemoval: true)]
    private $rewards;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $LastLogin = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $MattermostId = null;

    #[ORM\ManyToMany(targetEntity: AccessGroups::class, mappedBy: 'users')]
    private $accessGroups;

    #[ORM\Column(length: 255)]
    private ?string $authId = null;

    public function __construct()
    {
        $this->rewards = new ArrayCollection();
        $this->accessGroups = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->CreatedAt = new \DateTime();
        $this->UpdatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->UpdatedAt = new \DateTime();
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
        return (string) $this->password;
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

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->CreatedAt;
    }

    public function setCreatedAt(\DateTimeInterface $CreatedAt): self
    {
        $this->CreatedAt = $CreatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->UpdatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $UpdatedAt): self
    {
        $this->UpdatedAt = $UpdatedAt;

        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->LastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $LastLogin): self
    {
        $this->LastLogin = $LastLogin;

        return $this;
    }
    /**
     * @return Collection|Reward[]
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
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }
    public function setPlainPassword($plainPassword): void
    {
        $this->plainPassword = $plainPassword;
        $this->password = null;
    }

    /**
     * @return Collection|AccessGroups[]
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
            return 'user: ' . $this->id;
        }
    }
    public function getLocale(): string
    {
        return $this->member->getLocale();
    }

    public function getAuthId(): ?string
    {
        return $this->authId;
    }

    public function setAuthId(string $authId): static
    {
        $this->authId = $authId;

        return $this;
    }
}
