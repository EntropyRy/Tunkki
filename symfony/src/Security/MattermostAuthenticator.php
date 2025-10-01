<?php

namespace App\Security;

use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class MattermostAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlG,
    ) {
    }

    #[\Override]
    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return '_entropy_mattermost_check' ===
            $request->attributes->get('_route');
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('mattermost');
        $accessToken = $this->fetchAccessToken($client);
        $session = $request->getSession();
        assert($session instanceof Session);
        $fb = $session->getFlashBag();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use (
                $accessToken,
                $client,
                $fb,
            ) {
                $mmUser = $client->fetchUserFromToken($accessToken);
                $mmUserA = $mmUser->toArray();
                $email = $mmUserA['email'];
                $username = $mmUserA['username'];
                $id = $mmUserA['id'];

                // 1) have they logged in with Mattermost before? Easy!
                $existingUser = $this->em
                    ->getRepository(User::class)
                    ->findOneBy(['MattermostId' => $id]);

                if (null !== $existingUser) {
                    if (
                        strtolower(
                            (string) $existingUser->getMember()->getUsername(),
                        ) != $username
                    ) {
                        $existingUser->getMember()->setUsername($username);
                        $this->em->persist($existingUser);
                        $this->em->flush();
                        $fb->add(
                            'success',
                            'Your username was updated to your Mattermost username',
                        );
                    }

                    return $existingUser;
                }

                // 2) do we have a matching user by email?
                $member = $this->em
                    ->getRepository(Member::class)
                    ->findOneBy(['email' => $email]);
                if (null !== $member) {
                    if (
                        strtolower((string) $member->getUsername()) != $username
                    ) {
                        $member->setUsername($username);
                        $this->em->persist($member);
                        $this->em->flush();
                        $fb->add(
                            'success',
                            'Your username was updated to your Mattermost username',
                        );
                    }
                    $user = $member->getUser();
                    $user->setMattermostId($id);
                    $this->em->persist($user);
                    $this->em->flush();

                    return $user;
                }
                // No matching user found; fail authentication and show a translated flash
                throw new AuthenticationException('login.join_us_plain');
            }),
        );
    }

    #[\Override]
    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception,
    ): ?Response {
        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add('warning', 'login.join_us_plain');
            $session->remove('_security.last_error');
            $session->set('auth.mm.failure', true);
        }

        return new RedirectResponse($this->urlG->generate('app_login'));
    }

    #[\Override]
    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName,
    ): ?Response {
        if (
            $targetPath = $this->getTargetPath(
                $request->getSession(),
                $firewallName,
            )
        ) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse(
            $this->urlG->generate('dashboard.'.$request->getLocale()),
        );
    }

    #[\Override]
    public function start(
        Request $request,
        ?AuthenticationException $authException = null,
    ): RedirectResponse {
        return new RedirectResponse(
            '/login/', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT,
        );
    }
}
