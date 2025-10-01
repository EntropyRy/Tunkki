<?php

namespace App\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Class MattermostResourceOwner.
 */
class MattermostResourceOwner implements ResourceOwnerInterface
{
    /**
     * MattermostResourceOwner constructor.
     */
    public function __construct(protected array $response)
    {
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    #[\Override]
    public function toArray()
    {
        return $this->response;
    }

    /**
     * Get user id.
     *
     * @return string|null
     */
    #[\Override]
    public function getId()
    {
        return $this->response['id'] ?: null;
    }

    /**
     * Get user name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->response['name'] ?: null;
    }

    /**
     * Get user first name.
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->response['first_name'] ?: null;
    }

    /**
     * Get user last name.
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->response['last_name'] ?: null;
    }

    /**
     * Get user real name.
     *
     * @return string|null
     */
    public function getRealName()
    {
        return $this->response['real_name'] ?: null;
    }

    /**
     * Get user email.
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->response['email'] ?: null;
    }
}
