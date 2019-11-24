<?php 
  
namespace Entropy\TunkkiBundle\Entity; 
  
use Sonata\UserBundle\Entity\BaseUser;
use Doctrine\ORM\Mapping as ORM; 
  
/** 
 * User 
 * 
 * @ORM\MappedSuperclass 
 */ 
class User extends BaseUser
{
    /**
     *
     * @ORM\OneToOne(targetEntity="\Entropy\TunkkiBundle\Entity\Member", inversedBy="user")
     */
    private $member;

	public function __construct()
	{
		parent::__construct();
	}


    /**
     * Set member.
     *
     * @param \Entropy\TunkkiBundle\Entity\Member|null $member
     *
     * @return User
     */
    public function setMember(\Entropy\TunkkiBundle\Entity\Member $member = null)
    {
        $this->member = $member;

        return $this;
    }

    /**
     * Get member.
     *
     * @return \Entropy\TunkkiBundle\Entity\Member|null
     */
    public function getMember()
    {
        return $this->member;
    }
}
