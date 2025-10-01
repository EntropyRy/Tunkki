<?php

namespace App\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Mattermost.
 *
 * @author Harri Häivälä <webmaster@entropy.fi>
 */
class Mattermost extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * Returns the base URL for authorizing a client.
     *
     * @return string
     */
    #[\Override]
    public function getBaseAuthorizationUrl()
    {
        return 'https://chat.entropy.fi/oauth/authorize';
    }

    /**
     * Returns the base URL for requesting an access token.
     *
     * @return string
     */
    #[\Override]
    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://chat.entropy.fi/oauth/access_token';
    }

    /**
     * Returns the URL for requesting the resource owner's details.
     *
     * @return string
     */
    #[\Override]
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        /*   $params = [
               'token' => $token->getToken(),
           ];
           return 'https://chat.entropy.fi/api/v4/user/'.http_build_query($params);
           */
        return 'https://chat.entropy.fi/api/v4/users/me';
    }

    /**
     * Checks a provider response for errors.
     *
     * @param array|string $data Parsed response data
     *
     * @return void
     *
     * @throws IdentityProviderException
     */
    #[\Override]
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            $error = $data['error']['message'] ?? '';
            $code = isset($data['error']['code']) ? intval($data['error']['code']) : 0;
            throw new IdentityProviderException($error, $code, $data);
        }
    }

    /**
     * Create new resources owner using the generated access token.
     *
     * @return MattermostResourceOwner
     */
    #[\Override]
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new MattermostResourceOwner($response);
    }

    /**
     * @return array
     */
    #[\Override]
    protected function getDefaultScopes()
    {
        return [];
    }
}
