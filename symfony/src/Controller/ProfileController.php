<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\MemberType;
use App\Form\MemberEditType;
use App\Form\ActiveMemberType;
use App\Entity\Member;
use App\Entity\User;
use App\Form\UserPasswordType;
use App\Helper\Barcode;
use App\Helper\Mattermost;
use App\Repository\MemberRepository;
use App\Repository\EmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Form\EPicsPasswordType;
use App\Helper\ePics;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProfileController extends AbstractController
{
    #[
        Route(
            path: [
                "en" => "/profile/new",
                "fi" => '/profiili/uusi
    ',
            ],
            name: "profile_new",
        ),
    ]
    public function newMember(
        Request $request,
        MemberRepository $memberRepo,
        EmailRepository $emailRepo,
        UserPasswordHasherInterface $hasher,
        Mattermost $mm,
        MailerInterface $mailer,
        Barcode $bc,
        EntityManagerInterface $em,
    ): Response {
        $member = new Member();
        $email_content = null;
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $member = $form->getData();
                $name = $memberRepo->getByName(
                    $member->getFirstname(),
                    $member->getLastname(),
                );
                $email = $memberRepo->getByEmail($member->getEmail());
                if (!$name && !$email) {
                    $user = $member->getUser();
                    $user->setPassword(
                        $hasher->hashPassword(
                            $user,
                            $form->get("user")->get("plainPassword")->getData(),
                        ),
                    );
                    $member->setLocale($request->getLocale());
                    $member->setCode($bc->getCode());
                    $user->setAuthId(bin2hex(openssl_random_pseudo_bytes(10)));
                    $em->persist($user);
                    $em->persist($member);
                    $em->flush();

                    $email_content = $emailRepo->findOneBy([
                        "purpose" => "member",
                    ]);
                    $this->announceToMattermost($mm, $member->getName());
                    if ($email_content) {
                        $this->sendEmailToMember(
                            $email_content,
                            $member,
                            $mailer,
                        );
                    }
                    $this->addFlash("info", "member.join.added");
                    $this->redirectToRoute("app_login");
                } else {
                    $this->addFlash("warning", "member.join.update");
                }
            } else {
                $this->addFlash("danger", $form->getErrors());
            }
        }
        return $this->render("member/new.html.twig", [
            "form" => $form,
            "email" => $email_content,
        ]);
    }
    protected function sendEmailToMember($email_content, $member, $mailer): void
    {
        $email = new TemplatedEmail()
            ->from(new Address("webmaster@entropy.fi", "Entropy Webmaster"))
            ->to($member->getEmail())
            ->subject($email_content->getSubject())
            ->htmlTemplate("emails/member.html.twig")
            ->context([
                "body" => $email_content->getBody(),
            ]);
        $mailer->send($email);
    }
    protected function announceToMattermost($mm, string $member): void
    {
        $text = "**New Member: " . $member . "**";
        $mm->SendToMattermost($text, "yhdistys");
    }
    #[
        Route(
            path: [
                "en" => "/dashboard",
                "fi" => '/yleiskatsaus
    ',
            ],
            name: "dashboard",
        ),
    ]
    public function dashboard(Barcode $bc): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        // $barcode = $bc->getBarcode($member);
        return $this->render("profile/dashboard.html.twig", [
            "member" => $member,
            //'barcode' => $barcode
        ]);
    }
    #[
        Route(
            path: [
                "en" => "/profile",
                "fi" => '/profiili
    ',
            ],
            name: "profile",
        ),
    ]
    public function index(): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        return $this->render("profile/main.html.twig", [
            "member" => $member,
        ]);
    }
    #[
        Route(
            path: [
                "en" => "/profile/edit",
                "fi" => "/profiili/muokkaa",
            ],
            name: "profile_edit",
        ),
    ]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $form = $this->createForm(MemberEditType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            $em->persist($member);
            $em->flush();
            $request->setLocale($member->getLocale());
            $this->addFlash("success", "profile.member_data_changed");
            return $this->redirectToRoute("profile." . $member->getLocale());
        }
        return $this->render("profile/edit.html.twig", [
            "member" => $member,
            "form" => $form,
        ]);
    }
    #[
        Route(
            path: [
                "en" => "/profile/password",
                "fi" => "/profiili/salasana",
            ],
            name: "profile_password_edit",
        ),
    ]
    public function password(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        $form = $this->createForm(UserPasswordType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user->setPassword(
                $hasher->hashPassword(
                    $user,
                    $form->get("plainPassword")->getData(),
                ),
            );
            $em->persist($user);
            $em->flush();
            $this->addFlash("success", "profile.member_data_changed");
            return $this->redirectToRoute("profile");
        }
        return $this->render("profile/epics_password.html.twig", [
            "form" => $form,
            "epics_username" => $resolvedUsername,
        ]);
    }
    #[
        Route(
            path: [
                "en" => "/profile/apply",
                "fi" => "/profiili/aktiiviksi",
            ],
            name: "apply_for_active_member",
        ),
    ]
    public function apply(
        Request $request,
        Mattermost $mm,
        EntityManagerInterface $em,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        if ($member->getIsActiveMember()) {
            $this->addFlash("success", "profile.you_are_active_member_already");
            return $this->redirectToRoute("profile." . $member->getLocale());
        }
        $form = $this->createForm(ActiveMemberType::class, $member);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $form->getData();
            if (empty($member->getApplicationDate())) {
                $text = "**Active member application by " . $member . "**";
                $mm->SendToMattermost($text, "yhdistys");
            }
            $member->setApplicationDate(new \DateTime());
            $em->persist($member);
            $em->flush();
            $this->addFlash("success", "profile.application_saved");
            return $this->redirectToRoute("profile." . $member->getLocale());
        }
        return $this->render("profile/apply.html.twig", [
            "form" => $form,
        ]);
    }

    #[
        Route(
            path: [
                "en" => "/profile/epics/password",
                "fi" => "/profiili/epics/salasana",
            ],
            name: "profile_epics_password",
        ),
    ]
    public function epicsPassword(
        Request $request,
        ePics $epics,
        HttpClientInterface $client,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        assert($user instanceof User);
        $member = $user->getMember();
        $resolvedUsername =
            $member->getEpicsUsername() ?:
            $member->getUsername() ?? (string) $member->getId();

        $form = $this->createForm(EPicsPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get("plainPassword")->getData();
            $username =
                $member->getEpicsUsername() ?:
                $member->getUsername() ?? (string) $member->getId();
            $apiBase = "https://epics.entropy.fi";

            // Establish anonymous session for cookies and XSRF
            $initResponse = $client->request("GET", $apiBase, [
                "max_duration" => 5,
            ]);
            $headers = $initResponse->getHeaders();
            $sessionToken = null;
            $xsrfToken = null;

            if (isset($headers["set-cookie"])) {
                foreach ($headers["set-cookie"] as $cookie) {
                    if (str_starts_with($cookie, "XSRF-TOKEN=")) {
                        $parts = explode(";", $cookie);
                        $tokenValue = substr($parts[0], 11);
                        $xsrfToken = str_replace("%3D", "=", $tokenValue);
                    }
                    if (str_starts_with($cookie, "lychee_session=")) {
                        $parts = explode(";", $cookie);
                        $sessionValue = substr($parts[0], 15);
                        $sessionToken = str_replace("%3D", "=", $sessionValue);
                    }
                }
            }

            if (!$sessionToken || !$xsrfToken) {
                $this->addFlash("danger", "epics.password_set_failed");
                return $this->redirectToRoute(
                    "profile." . $member->getLocale(),
                );
            }

            $adminUser =
                $_ENV["EPICS_ADMIN_USER"] ??
                ($_SERVER["EPICS_ADMIN_USER"] ?? null);
            $adminPass =
                $_ENV["EPICS_ADMIN_PASSWORD"] ??
                ($_SERVER["EPICS_ADMIN_PASSWORD"] ?? null);

            if (!$adminUser || !$adminPass) {
                $this->addFlash("danger", "epics.password_set_failed");
                return $this->redirectToRoute(
                    "profile." . $member->getLocale(),
                );
            }

            $commonHeaders = [
                "Cookie" => "lychee_session=" . $sessionToken,
                "X-XSRF-TOKEN" => $xsrfToken,
                "Accept" => "application/json",
                "Content-Type" => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer" => $apiBase . "/",
            ];

            // Login as admin
            $loginResp = $client->request(
                "POST",
                $apiBase . "/api/v2/Session::login",
                [
                    "max_duration" => 10,
                    "headers" => $commonHeaders,
                    "json" => [
                        "username" => $adminUser,
                        "password" => $adminPass,
                    ],
                ],
            );
            if ($loginResp->getStatusCode() !== 200) {
                $this->addFlash("danger", "epics.password_set_failed");
                return $this->redirectToRoute(
                    "profile." . $member->getLocale(),
                );
            }

            // Check if user exists (try multiple endpoints)
            $exists = false;
            $userId = null;

            try {
                $listResp = $client->request(
                    "POST",
                    $apiBase . "/api/v2/Users::list",
                    [
                        "max_duration" => 10,
                        "headers" => $commonHeaders,
                        "json" => [],
                    ],
                );
                if ($listResp->getStatusCode() === 200) {
                    $data = json_decode($listResp->getContent(false), true);
                    $arr = $data["users"] ?? ($data ?? []);
                    if (is_array($arr)) {
                        foreach ($arr as $u) {
                            $uname = $u["username"] ?? ($u["name"] ?? null);
                            if ($uname === $username) {
                                $exists = true;
                                $userId = $u["id"] ?? null;
                                break;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore; try next method
            }

            if (!$exists) {
                try {
                    $getResp = $client->request(
                        "POST",
                        $apiBase . "/api/v2/Users::get",
                        [
                            "max_duration" => 10,
                            "headers" => $commonHeaders,
                            "json" => ["username" => $username],
                        ],
                    );
                    if ($getResp->getStatusCode() === 200) {
                        $ud = json_decode($getResp->getContent(false), true);
                        // Accept both object and wrapped forms
                        $exists = true;
                        $userId = $ud["id"] ?? ($ud["user"]["id"] ?? null);
                    }
                } catch (\Throwable $e) {
                    // ignore; treat as not found
                }
            }

            $overallOk = false;

            if ($exists) {
                // Reset password
                foreach (
                    ["/api/v2/User::setPassword", "/api/v2/Users::setPassword"]
                    as $ep
                ) {
                    try {
                        $payload = $userId
                            ? ["id" => $userId, "password" => $plain]
                            : ["username" => $username, "password" => $plain];
                        $resp = $client->request("POST", $apiBase . $ep, [
                            "max_duration" => 10,
                            "headers" => $commonHeaders,
                            "json" => $payload,
                        ]);
                        if ($resp->getStatusCode() === 200) {
                            $overallOk = true;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // try next
                    }
                }
            } else {
                // Create user
                try {
                    $createResp = $client->request(
                        "POST",
                        $apiBase . "/api/v2/Users::add",
                        [
                            "max_duration" => 10,
                            "headers" => $commonHeaders,
                            "json" => [
                                "username" => $username,
                                "password" => $plain,
                            ],
                        ],
                    );
                    if ($createResp->getStatusCode() === 200) {
                        $overallOk = true;
                        // Try to capture ID from response if provided
                        $cd = json_decode($createResp->getContent(false), true);
                        $userId = $cd["id"] ?? ($cd["user"]["id"] ?? $userId);
                        $exists = true;
                    }
                } catch (\Throwable $e) {
                    $overallOk = false;
                }
            }

            // Ensure we have userId (try to resolve post-create)
            if ($exists && !$userId) {
                try {
                    $getResp = $client->request(
                        "POST",
                        $apiBase . "/api/v2/Users::get",
                        [
                            "max_duration" => 10,
                            "headers" => $commonHeaders,
                            "json" => ["username" => $username],
                        ],
                    );
                    if ($getResp->getStatusCode() === 200) {
                        $ud = json_decode($getResp->getContent(false), true);
                        $userId = $ud["id"] ?? ($ud["user"]["id"] ?? $userId);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // Grant permissions: upload + modify own profile
            if ($exists) {
                foreach (["/api/v2/User::set", "/api/v2/Users::set"] as $ep) {
                    try {
                        $payload = [
                            "may_upload" => true,
                            "may_edit_own_settings" => true,
                        ];
                        if ($userId) {
                            $payload["id"] = $userId;
                        } else {
                            $payload["username"] = $username;
                        }
                        $resp = $client->request("POST", $apiBase . $ep, [
                            "max_duration" => 10,
                            "headers" => $commonHeaders,
                            "json" => $payload,
                        ]);
                        if ($resp->getStatusCode() === 200) {
                            break;
                        }
                    } catch (\Throwable $e) {
                        // try next
                    }
                }
            }

            if ($overallOk) {
                $this->addFlash(
                    "success",
                    'epics.password_set <a class="btn btn-sm btn-primary ms-2" href="' .
                        $apiBase .
                        '" target="_blank" rel="noopener">Login to ePics</a>',
                );
            } else {
                $this->addFlash("danger", "epics.password_set_failed");
            }

            return $this->redirectToRoute("profile." . $member->getLocale());
        }

        return $this->render("profile/password.html.twig", [
            "form" => $form,
        ]);
    }
}
