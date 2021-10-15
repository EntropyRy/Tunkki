<?php
namespace App\Security;

use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use KnpU\OAuth2ClientBundle\Client\Provider\SlackClient;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use App\Entity\Member;
use App\Entity\User;

class MattermostAuthenticator extends SocialAuthenticator
{
    use TargetPathTrait;
    private $clientRegistry;
    private $em;
    private $urlG;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $em, UrlGeneratorInterface $urlG)
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->urlG = $urlG;
    }
    public function supports(Request $request)
    {
                        // continue ONLY if the current ROUTE matches the check ROUTE
        return $request->attributes->get('_route') === '_entropy_mattermost_check';
    }
    public function getCredentials(Request $request)
    {
        return $this->fetchAccessToken($this->getMattermostClient());
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var FacebookUser $facebookUser */
        $mattermostUser = $this->getMattermostClient()
            ->fetchUserFromToken($credentials);
        if(!$mattermostUser){
             throw new CustomUserMessageAuthenticationException('Email could not be found.');
        }
        $email = $mattermostUser->getEmail();
        // 1) have they logged in with Mattermost before? Easy!
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['MattermostId' => $mattermostUser->getId()]);
        if ($existingUser) {
            return $existingUser;
        }

        // 2) do we have a matching user by email?
        $member = $this->em->getRepository(Member::class)
                    ->findOneBy(['email' => $email]);
        if (!$member) {
             // fail authentication with a custom error
             throw new CustomUserMessageAuthenticationException('Email could not be found.');
        }
        $user = $member->getUser();
        $user->setMattermostId($mattermostUser->getId());
        $this->em->persist($user);
        $this->em->flush();

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

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $user = $token->getUser();
        $user->setLastLogin(new \DateTime());
        $this->em->persist($user);
        $this->em->flush();
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }
        if(in_array("ROLE_ADMIN", $user->getRoles()) || in_array("ROLE_SUPER_ADMIN", $user->getRoles())){
            return new RedirectResponse($this->urlG->generate('sonata_admin_dashboard'));
        } else {
            return new RedirectResponse('/');
        }
    }
   
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse(
            '/login/', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    } 
}
