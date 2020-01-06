<?php 
  
namespace App\Entity; 
  
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sonata\UserBundle\Entity\BaseUser;
use Doctrine\ORM\Mapping as ORM; 
use App\Entity\Reward;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user_user")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reward", mappedBy="user", orphanRemoval=true)
     */
    private $rewards;
    /**
     *
     * @ORM\OneToOne(targetEntity="\App\Entity\Member", inversedBy="user")
     */
    private $member;

    /**
     * Get id.
     *
     * @return int $id
     */
    public function getId()
    {
        return $this->id;
    }

    public function __construct()
    {
        parent::__construct();
        $this->rewards = new ArrayCollection();
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

    /**
     * Set member.
     *
     * @param Member|null $member
     *
     * @return User
     */
    public function setMember(Member $member = null)
    {
        $this->member = $member;

        return $this;
    }

    /**
     * Get member.
     *
     * @return Member|null
     */
    public function getMember()
    {
        return $this->member;
    }

}
