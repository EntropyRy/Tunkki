<?php

namespace App\Entity;

use App\Application\Sonata\UserBundle\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Member
 *
 * @ORM\Table(name="member")
 * @ORM\Entity(repositoryClass="App\Repository\MemberRepository")
 */
class Member
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=190)
     */
    private $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=190)
     */
    private $lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=190, nullable=true)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=190, nullable=true)
     */
    private $username;

    /**
     * @var string|null
     *
     * @ORM\Column(name="phone", type="string", length=190, nullable=true)
     */
    private $phone;

    /**
     * @var string|null
     *
     * @ORM\Column(name="CityOfResidence", type="string", length=190, nullable=true)
     */
    private $CityOfResidence;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="createdAt", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="updatedAt", type="datetime")
     */
    private $updatedAt;

    /**
     * @var bool
     *
     * @ORM\Column(name="CopiedAsUser", type="boolean")
     */
    private $copiedAsUser = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="isActiveMember", type="boolean")
     */
    private $isActiveMember = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="rejectReasonSent", type="boolean")
     */
    private $rejectReasonSent = false;

	/**
     * @var boolean
     *
     * @ORM\Column(name="StudentUnionMember", type="boolean")
     */
    private $StudentUnionMember = false;

    /**
     * @var string
     * 
     * @ORM\Column(name="Application", type="text", nullable=true)
     */
    private $Application;

    /**
     * @var string
     * 
     * @ORM\Column(name="reject_reason", type="text", nullable=true)
     */
    private $rejectReason;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="ApplicationDate", type="datetime", nullable=true)
     */
    private $ApplicationDate;

    /**
     * @var \DateTime
     * 
     * @ORM\Column(name="ApplicationHandledDate", type="datetime", nullable=true)
     */
    private $ApplicationHandledDate;

    /**
     *
     * @ORM\OneToOne(targetEntity="App\Application\Sonata\UserBundle\Entity\User", mappedBy="member")
     */
    private $user;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->firstname.' '.$this->lastname;
    }

    /**
     * Set email.
     *
     * @param string $email
     *
     * @return Member
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set phone.
     *
     * @param string|null $phone
     *
     * @return Member
     */
    public function setPhone($phone = null)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get phone.
     *
     * @return string|null
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Member
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt.
     *
     * @param \DateTime $updatedAt
     *
     * @return Member
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
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
     * Set cityOfResidence.
     *
     * @param string|null $cityOfResidence
     *
     * @return Member
     */
    public function setCityOfResidence($cityOfResidence = null)
    {
        $this->CityOfResidence = $cityOfResidence;

        return $this;
    }

    /**
     * Get cityOfResidence.
     *
     * @return string|null
     */
    public function getCityOfResidence()
    {
        return $this->CityOfResidence;
    }

    /**
     * Set user.
     *
     * @param \App\Entity\User|null $user
     *
     * @return Member
     */
    public function setUser(\App\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return \App\Entity\User|null
     */
    public function getUser()
    {
        return $this->user;
    }

    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Set firstname.
     *
     * @param string $firstname
     *
     * @return Member
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set lastname.
     *
     * @param string $lastname
     *
     * @return Member
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname.
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set copiedAsUser.
     *
     * @param bool $copiedAsUser
     *
     * @return Member
     */
    public function setCopiedAsUser($copiedAsUser)
    {
        $this->copiedAsUser = $copiedAsUser;

        return $this;
    }

    /**
     * Get copiedAsUser.
     *
     * @return bool
     */
    public function getCopiedAsUser()
    {
        return $this->copiedAsUser;
    }

    /**
     * Set isActiveMember.
     *
     * @param bool $isActiveMember
     *
     * @return Member
     */
    public function setIsActiveMember($isActiveMember)
    {
        $this->isActiveMember = $isActiveMember;

        return $this;
    }

    /**
     * Get isActiveMember.
     *
     * @return bool
     */
    public function getIsActiveMember()
    {
        return $this->isActiveMember;
    }

    /**
     * Set rejectReason.
     *
     * @param string|null $rejectReason
     *
     * @return Member
     */
    public function setRejectReason($rejectReason = null)
    {
        $this->rejectReason = $rejectReason;

        return $this;
    }

    /**
     * Get rejectReason.
     *
     * @return string|null
     */
    public function getRejectReason()
    {
        return $this->rejectReason;
    }

    /**
     * Set rejectReasonSent.
     *
     * @param bool $rejectReasonSent
     *
     * @return Member
     */
    public function setRejectReasonSent($rejectReasonSent)
    {
        $this->rejectReasonSent = $rejectReasonSent;

        return $this;
    }

    /**
     * Get rejectReasonSent.
     *
     * @return bool
     */
    public function getRejectReasonSent()
    {
        return $this->rejectReasonSent;
    }


    /**
     * Set applicationHandledDate.
     *
     * @param \DateTime|null $applicationHandledDate
     *
     * @return Member
     */
    public function setApplicationHandledDate($applicationHandledDate = null)
    {
        $this->ApplicationHandledDate = $applicationHandledDate;

        return $this;
    }

    /**
     * Get applicationHandledDate.
     *
     * @return \DateTime|null
     */
    public function getApplicationHandledDate()
    {
        return $this->ApplicationHandledDate;
    }

    /**
     * Set username.
     *
     * @param string|null $username
     *
     * @return Member
     */
    public function setUsername($username = null)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username.
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->username;
    }
}
