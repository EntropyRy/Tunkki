<?php
namespace Entropy\TunkkiBundle\Provider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
/**
 * Class MattermostResourceOwner
 *
 */
class MattermostResourceOwner implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected $response;
    /**
     * Token
     *
     * @var \League\OAuth2\Client\Token\AccessToken
     */
    protected $token;
    /**
     * MattermostResourceOwner constructor.
     *
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
        $this->token = $token;
    }
    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
    /**
     * Get user id
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->response['user']['id'] ?: null;
    }
    /**
     * Get user name
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->response['user']['name'] ?: null;
    }
    /**
     * Is user deleted?
     *
     * @return bool|null
     */
    public function isDeleted()
    {
        return $this->response['user']['deleted'] ?: null;
    }
    /**
     * Get user color
     *
     * @return string|null
     */
    public function getColor()
    {
        return $this->response['user']['color'] ?: null;
    }
    /**
     * Get user profile
     *
     * @return string|null
     */
    public function getProfile()
    {
        return $this->response['user']['profile'] ?: null;
    }
    /**
     * Get user first name
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->response['user']['profile']['first_name'] ?: null;
    }
    /**
     * Get user last name
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->response['user']['profile']['last_name'] ?: null;
    }
    /**
     * Get user real name
     *
     * @return string|null
     */
    public function getRealName()
    {
        return $this->response['user']['profile']['real_name'] ?: null;
    }
    /**
     * Get user email
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->response['email'] ?: null;
    }
    public function isAdmin()
    {
        return $this->response['user']['is_admin'] ?: null;
    }
    /**
     * Is user owner?
     *
     * @return string|null
     */
    public function isOwner()
    {
        return $this->response['user']['is_owner'] ?: null;
    }
    /**
     * Does user have 2FA enabled?
     *
     * @return bool|null
     */
    public function hasTwoFactorAuthentication()
    {
        return $this->response['user']['has_2fa'] ?: null;
    }
    /**
     * Does user have files?
     *
     * @return bool|null
     */
    public function hasFiles()
    {
        return $this->response['user']['has_files'] ?: null;
    }
}
