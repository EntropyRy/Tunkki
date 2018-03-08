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
     * @var boolean
     *
     * @ORM\Column(name="StudentUnionMember", type="boolean", nullable=true)
     */
    private $StudentUnionMember;

    /**
     * @var string
     * 
     * @ORM\Column(name="Application", type="text", nullable=true)
     */
    private $Application;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="ApplicationDate", type="datetime", nullable=true)
     */
    private $ApplicationDate;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="ApplicationAcceptedDate", type="datetime", nullable=true)
     */

	private $ApplicationAcceptedDate;

	public function __construct()
	{
		parent::__construct();
	}

    /**
     * Set studentUnionMember.
     *
     * @param bool|null $studentUnionMember
     *
     * @return User
     */
    public function setStudentUnionMember($studentUnionMember = null)
    {
        $this->StudentUnionMember = $studentUnionMember;

        return $this;
    }

    /**
     * Get studentUnionMember.
     *
     * @return bool|null
     */
    public function getStudentUnionMember()
    {
        return $this->StudentUnionMember;
    }

    /**
     * Set application.
     *
     * @param string|null $application
     *
     * @return User
     */
    public function setApplication($application = null)
    {
        $this->Application = $application;

        return $this;
    }

    /**
     * Get application.
     *
     * @return string|null
     */
    public function getApplication()
    {
        return $this->Application;
    }

    /**
     * Set applicationDate.
     *
     * @param \DateTime|null $applicationDate
     *
     * @return User
     */
    public function setApplicationDate($applicationDate = null)
    {
        $this->ApplicationDate = $applicationDate;

        return $this;
    }

    /**
     * Get applicationDate.
     *
     * @return \DateTime|null
     */
    public function getApplicationDate()
    {
        return $this->ApplicationDate;
    }

    /**
     * Set applicationAcceptedDate.
     *
     * @param \DateTime|null $applicationAcceptedDate
     *
     * @return User
     */
    public function setApplicationAcceptedDate($applicationAcceptedDate = null)
    {
        $this->ApplicationAcceptedDate = $applicationAcceptedDate;

        return $this;
    }

    /**
     * Get applicationAcceptedDate.
     *
     * @return \DateTime|null
     */
    public function getApplicationAcceptedDate()
    {
        return $this->ApplicationAcceptedDate;
    }
}
