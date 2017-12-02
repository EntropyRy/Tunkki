<?php
namespace Entropy\TunkkiBundle\Security;

use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\RouterInterface;
use KnpU\OAuth2ClientBundle\Client\Provider\SlackClient;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;

abstract class MattermostAuthenticator extends SocialAuthenticator
{
    private $clientRegistry;
    private $em;
    private $router;

    public function __construct(ClientRegistry $clientRegistry, EntityManager $em, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->router = $router;
    }

    public function getCredentials(Request $request)
    {
        if ($request->getPathInfo() != '/oauth') {
            // don't auth
            return;
        }

        return $this->fetchAccessToken($this->getSlackClient());
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var FacebookUser $facebookUser */
        $mattermostUser = $this->getSlackClient()
            ->fetchUserFromToken($credentials);

        $email = $mattermostUser->getEmail();

        // 1) have they logged in with Mattermost before? Easy!
        $existingUser = $this->em->getRepository('ApplicationSonataUserBundle:User')
            ->findOneBy(['mattermostId' => $mattermostUser->getId()]);
        if ($existingUser) {
            return $existingUser;
        }

        // 2) do we have a matching user by email?
        $user = $this->em->getRepository('ApplicationSonataUserBundle:User')
                    ->findOneBy(['email' => $email]);

        // 3) Maybe you just want to "register" them by creating
        // a User object
        //$user->setFacebookId($facebookUser->getId());
        //$this->em->persist($user);
        //$this->em->flush();

        return $user;
    }
    
    /**
     * @return SlackClient
     */
    private function getMattermostClient()
    {
        return $this->clientRegistry
            ->getClient('mattermost');
    }

/*    public function onAuthenticationFailure()
    {
    }

    public function onAuthenticationSuccess()
    {
    }
    
    private function start(Request $request, AuthenticationException $authException = null)
    {

    }*/
}
