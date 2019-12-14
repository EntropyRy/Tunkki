<?php 
  
namespace App\Entity; 
  
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
     * @ORM\OneToOne(targetEntity="\App\Entity\Member", inversedBy="user")
     */
    private $member;

	public function __construct()
	{
		parent::__construct();
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
