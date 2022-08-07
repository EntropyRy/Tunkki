<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use App\Security\LoginFormAuthenticator;
use App\Entity\User;

class MattermostAuthController extends Controller
{
    protected $em;
    protected $registry;
    protected $authenticator;
    protected $guard;

    public function __construct(ClientRegistry $registry, LoginFormAuthenticator $authenticator, GuardAuthenticatorHandler $guard)
    {
        $this->guard = $guard;
        $this->registry = $registry;
        $this->authenticator = $authenticator;
    }
    public function connectAction()
    {
        return $this->registry
            ->getClient('mattermost')
            ->redirect();
    }
    public function connectCheckAction(Request $request)
    {
        /*    $client = $this->registry->getClient('mattermost');

            try {
                $mmuser = $client->fetchUser();
                $this->em = $this->get('doctrine');
                $user = $this->em->getRepository(User::class)
                    ->findOneBy(['email' => $mmuser->getEmail()]);
                if(!$user){
                    return $this->redirect($this->generateUrl('/'));
                }
                // OLD LOGIN
                /*$token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
                $this->get('security.token_storage')->setToken($token);
                $this->get('session')->set('_security_main', serialize($token));
                $event = new InteractiveLoginEvent($request, $token);
                $this->edp->dispatch("security.interactive_login", $event);

                if(in_array("ROLE_ADMIN", $user->getRoles()) || in_array("ROLE_SUPER_ADMIN", $user->getRoles())){
                    return $this->redirect($this->generateUrl('sonata_admin_dashboard'));
                } else {
                    return $this->redirect($this->generateUrl('/'));
                }
                return $this->guard->authenticateUserAndHandleSuccess(
                    $user,
                    $request,
                    $this->authenticator,
                    'main' // firewall name in security.yaml
                );

            } catch (IdentityProviderException $e) {
                var_dump($e->getMessage());die;
            }*/
    }
}
