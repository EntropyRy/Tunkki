<?php

declare(strict_types=1);

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

}
