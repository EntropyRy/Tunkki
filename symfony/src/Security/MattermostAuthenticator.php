<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\Member;
use App\Entity\User;

class MattermostAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly UrlGeneratorInterface $urlG,
        private readonly RequestStack $rs
    ) {
    }
    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return $request->attributes->get('_route') === '_entropy_mattermost_check';
    }
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('mattermost');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                $mmUser = $client->fetchUserFromToken($accessToken);
                $mmUserA = $mmUser->toArray();
                $email = $mmUserA['email'];
                $username = $mmUserA['username'];
                $id = $mmUserA['id'];

                // 1) have they logged in with Mattermost before? Easy!
                $existingUser = $this->em->getRepository(User::class)->findOneBy(['MattermostId' => $id]);

                if ($existingUser) {
                    if (strtolower($existingUser->getMember()->getUsername()) != $username) {
                        $existingUser->getMember()->setUsername($username);
                        $this->em->persist($existingUser);
                        $this->em->flush();
                        $this->rs->getSession()->getFlashBag()->add('success', 'Your username was updated to your Mattermost username');
                    }
                    return $existingUser;
                }

                // 2) do we have a matching user by email?
                $member = $this->em->getRepository(Member::class)->findOneBy(['email' => $email]);
                if ($member) {
                    if (strtolower($member->getUsername()) != $username) {
                        $member->setUsername($username);
                        $this->em->persist($member);
                        $this->em->flush();
                        $this->rs->getSession()->getFlashBag()->add('success', 'Your username was updated to your Mattermost username');
                    }
                    $user = $member->getUser();
                    $user->setMattermostId($id);
                    $this->em->persist($user);
                    $this->em->flush();
                    return $user;
                }
                $this->rs->getSession()->getFlashBag()->add('warning', 'user not found');
            })
        );
    }
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }
        return new RedirectResponse($this->urlG->generate('dashboard.' . $request->getLocale()));
    }

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse(
            '/login/', // might be the site, where users choose their oauth provider
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
