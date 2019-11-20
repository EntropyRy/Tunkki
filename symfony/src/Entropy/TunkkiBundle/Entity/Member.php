<?php

namespace Entropy\TunkkiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Member
 *
 * @ORM\Table(name="member")
 * @ORM\Entity(repositoryClass="Entropy\TunkkiBundle\Repository\MemberRepository")
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     */
    private $email;

    /**
     * @var string|null
     *
     * @ORM\Column(name="phone", type="string", length=255, nullable=true)
     */
    private $phone;

    /**
     * @var string|null
     *
     * @ORM\Column(name="HomeCity", type="string", length=255, nullable=true)
     */
    private $homeCity;

    /**
     * @var bool
     *
     * @ORM\Column(name="AYYMembership", type="boolean")
     */
    private $aYYMembership = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="createdAt", type="datetime")
     */
    private $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="updatedAt", type="datetime")
     */
    private $updatedAt;

    /**
     * @var bool
     *
     * @ORM\Column(name="CopiedAsMember", type="boolean")
     */
    private $copiedAsMember;


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
     * Set name.
     *
     * @param string $name
     *
     * @return Member
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * Set homeCity.
     *
     * @param string|null $homeCity
     *
     * @return Member
     */
    public function setHomeCity($homeCity = null)
    {
        $this->homeCity = $homeCity;

        return $this;
    }

    /**
     * Get homeCity.
     *
     * @return string|null
     */
    public function getHomeCity()
    {
        return $this->homeCity;
    }

    /**
     * Set aYYMembership.
     *
     * @param bool $aYYMembership
     *
     * @return Member
     */
    public function setAYYMembership($aYYMembership)
    {
        $this->aYYMembership = $aYYMembership;

        return $this;
    }

    /**
     * Get aYYMembership.
     *
     * @return bool
     */
    public function getAYYMembership()
    {
        return $this->aYYMembership;
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
     * Set copiedAsMember.
     *
     * @param bool $copiedAsMember
     *
     * @return Member
     */
    public function setCopiedAsMember($copiedAsMember)
    {
        $this->copiedAsMember = $copiedAsMember;

        return $this;
    }

    /**
     * Get copiedAsMember.
     *
     * @return bool
     */
    public function getCopiedAsMember()
    {
        return $this->copiedAsMember;
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
}
