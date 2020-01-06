<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Entity\User;

class MattermostAuthController extends Controller
{
    protected $em;
    protected $registry;
    protected $edp;
    public function __construct(ClientRegistry $registry, EventDispatcherInterface $edp)
    {
        $this->registry = $registry;
        $this->edp = $edp;
    }
    public function connectAction()
    {
        return $this->registry
            ->getClient('mattermost')
            ->redirect();
    }
    public function connectCheckAction(Request $request)
    {
        $client = $this->registry->getClient('mattermost');

        try {
            $mmuser = $client->fetchUser();
            $this->em = $this->get('doctrine');
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $mmuser->getEmail()]);
            if(!$user){
                return $this->redirect($this->generateUrl('sonata_admin_dashboard'));
            }
            // LOGIN
            $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
            $this->get('security.token_storage')->setToken($token);
            $this->get('session')->set('_security_main', serialize($token));
            $event = new InteractiveLoginEvent($request, $token);
            $this->edp->dispatch("security.interactive_login", $event);
            return $this->redirect($this->generateUrl('sonata_admin_dashboard'));
        } catch (IdentityProviderException $e) {
            var_dump($e->getMessage());die;
        }
    }
}
