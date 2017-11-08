<?php
namespace Entropy\TunkkiBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;


class MattermostAuthController extends Controller
{
    protected $em;
    public function connectAction()
    {
        return $this->get('oauth2.registry')
            ->getClient('mattermost')
            ->redirect();
    }
    public function connectCheckAction(Request $request)
    {
        $client = $this->get('oauth2.registry')
            ->getClient('mattermost');

        try {
            $mmuser = $client->fetchUser();
            $this->em = $this->container->get('doctrine.orm.entity_manager');
            $user = $this->em->getRepository('ApplicationSonataUserBundle:User')
                ->findOneBy(['email' => $mmuser->getEmail()]);
            // LOGIN
            $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
            $this->get('security.token_storage')->setToken($token);
            $this->get('session')->set('_security_main', serialize($token));
            $event = new InteractiveLoginEvent($request, $token);
            $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);
            return $this->redirect($this->generateUrl('sonata_admin_dashboard'));


        } catch (IdentityProviderException $e) {
            var_dump($e->getMessage());die;
        }
    }
}
