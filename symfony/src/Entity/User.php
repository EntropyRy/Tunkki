<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE", region="member")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     * @Assert\Length(min=8)
     */
    private $password;

    /**
     * @Assert\Length(min=8)
     */
    private $plainPassword;

    /**
     * @ORM\OneToOne(targetEntity=Member::class, inversedBy="user", cascade={"persist", "remove"})
     */
    private $member;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    private $CreatedAt;

    /**
     * @ORM\Column(type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private $UpdatedAt;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reward", mappedBy="user", orphanRemoval=true)
     */
    private $rewards;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $LastLogin;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $MattermostId;

    /**
     * @ORM\ManyToMany(targetEntity=AccessGroups::class, mappedBy="users")
     */
    private $accessGroups;

    public function __construct()
    {
        $this->rewards = new ArrayCollection();
        $this->accessGroups = new ArrayCollection();
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
        if ($this->member){
            if ($this->member->getUsername()) {
                return $this->member->getUsername();
            }
            if ($this->member->getEmail()) {
                return $this->member->getEmail();
            }
        } 
        return 'N/A';
    }
    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';
        foreach($this->accessGroups as $group){
            if($group->getActive()){
                foreach($group->getRoles() as $role){
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
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
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
    public function __toString()
    {
        if ($this->member){
            return $this->member->getName();
        } else {
            return 'user: '.$this->id;
        }
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
    public function setPlainPassword($plainPassword)
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
}
