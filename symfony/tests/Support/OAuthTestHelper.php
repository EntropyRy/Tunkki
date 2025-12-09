<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Entity\AccessToken as AccessTokenEntity;
use League\Bundle\OAuth2ServerBundle\Entity\Client as ClientEntity;
use League\Bundle\OAuth2ServerBundle\Entity\Scope as ScopeEntity;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use League\OAuth2\Server\CryptKey;

/**
 * Lightweight helpers for creating OAuth clients and access tokens in tests.
 */
trait OAuthTestHelper
{
    /**
     * Create (or reuse) a test OAuth2 client.
     *
     * @param string[] $redirectUris
     * @param string[] $grants
     * @param string[] $scopes
     */
    protected function createOAuthClient(
        string $identifier = 'test_wiki',
        array $redirectUris = ['https://wiki.example.test/callback'],
        array $grants = ['authorization_code', 'password', 'refresh_token', 'client_credentials'],
        array $scopes = ['wiki'],
    ): Client {
        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);

        $existing = $clientManager->find($identifier);
        if ($existing instanceof Client) {
            return $existing;
        }

        $client = new Client(
            'Test '.$identifier,
            $identifier,
            'test_secret_'.$identifier,
        );
        $client->setRedirectUris(
            ...array_map(static fn (string $uri): RedirectUri => new RedirectUri($uri), $redirectUris),
        );
        $client->setGrants(
            ...array_map(static fn (string $grant): Grant => new Grant($grant), $grants),
        );
        $client->setScopes(
            ...array_map(static fn (string $scope): Scope => new Scope($scope), $scopes),
        );

        $clientManager->save($client);

        return $client;
    }

    /**
     * Create and persist a bearer token for the given user and scopes.
     *
     * @param string[] $scopes
     */
    protected function createAccessToken(
        User $user,
        array $scopes = ['wiki'],
        string $clientIdentifier = 'test_wiki',
        ?\DateTimeInterface $expiresAt = null,
    ): string {
        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);
        /** @var AccessTokenManagerInterface $accessTokenManager */
        $accessTokenManager = static::getContainer()->get(AccessTokenManagerInterface::class);

        $client = $clientManager->find($clientIdentifier);
        if (!$client instanceof Client) {
            $client = $this->createOAuthClient($clientIdentifier, ['https://example.test/callback'], ['authorization_code'], $scopes);
        }

        $expiry = $expiresAt ?? new \DateTimeImmutable('+1 hour');
        $identifier = bin2hex(random_bytes(20));

        $accessTokenModel = new AccessToken(
            $identifier,
            $expiry,
            $client,
            $user->getUserIdentifier(),
            array_map(static fn (string $scope): Scope => new Scope($scope), $scopes),
        );
        $accessTokenManager->save($accessTokenModel);

        $privateKeyPath = sprintf('%s/config/secrets/test/oauth/private.key', static::getContainer()->getParameter('kernel.project_dir'));
        $accessTokenEntity = new AccessTokenEntity();
        $accessTokenEntity->setIdentifier($identifier);
        $accessTokenEntity->setExpiryDateTime(\DateTimeImmutable::createFromInterface($expiry));
        $accessTokenEntity->setClient($this->buildClientEntity($client));
        $accessTokenEntity->setUserIdentifier($user->getUserIdentifier());
        foreach ($scopes as $scope) {
            $scopeEntity = new ScopeEntity();
            $scopeEntity->setIdentifier($scope);
            $accessTokenEntity->addScope($scopeEntity);
        }
        $accessTokenEntity->setPrivateKey(new CryptKey($privateKeyPath));

        return $accessTokenEntity->toString();
    }

    private function buildClientEntity(Client $client): ClientEntity
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($client->getIdentifier());
        $clientEntity->setName($client->getName());
        $clientEntity->setRedirectUri(
            array_map(
                static fn (RedirectUri $uri): string => (string) $uri,
                $client->getRedirectUris(),
            ),
        );
        $clientEntity->setConfidential($client->isConfidential());
        $clientEntity->setAllowPlainTextPkce($client->isPlainTextPkceAllowed());

        return $clientEntity;
    }
}
