<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Email;
use App\Entity\User;
use App\Helper\Qr;
use App\Repository\ArtistRepository;
use App\Repository\MemberRepository;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @extends CRUDController<Email>
 */
final class EmailAdminController extends CRUDController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Qr $qr,
    ) {
    }

    public function sendProgressAction(): JsonResponse
    {
        $session = $this->requestStack->getSession();
        $progress = $session->get('email_send_progress', [
            'current' => 0,
            'total' => 0,
            'completed' => false,
        ]);

        // Make sure to return fresh data, not cached
        return new JsonResponse($progress, 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function previewAction(): Response
    {
        $email = $this->admin->getSubject();
        $event = $email->getEvent();
        $img = null;
        $qr = null;
        if (!is_null($event)) {
            $img = $event->getPicture();
            if ('ticket_qr' == $email->getPurpose()) {
                $qrGenerator = $this->qr;
                $qr = $qrGenerator->getQrBase64('test');
            }
        }
        $admin = $this->admin;

        return $this->render('emails/admin_preview.html.twig', [
            'body' => $email->getBody(),
            'qr' => $qr,
            'email' => $email,
            'admin' => $admin,
            'img' => $img,
        ]);
    }

    public function sendAction(
        MailerInterface $mailer,
        ArtistRepository $aRepo,
        MemberRepository $memberRepository,
    ): RedirectResponse|JsonResponse {
        $session = $this->requestStack->getSession();
        $email = $this->admin->getSubject();
        $links = $email->getAddLoginLinksToFooter();
        $purpose = $email->getPurpose();
        $subject = $email->getSubject();
        $event = $email->getEvent();
        $emails = [];
        $locales = [];
        $img = null;
        if ($event) {
            $img = $event->getPicture();
        }
        $body = $email->getBody();
        $count = 0;
        $replyto = $email->getReplyTo() ?: 'hallitus@entropy.fi';
        if ($subject && $body) {
            if ('rsvp' == $purpose && $event) {
                $rsvps = $event->getRSVPs();
                if (count($rsvps) > 0) {
                    foreach ($rsvps as $rsvp) {
                        $emails[] = $rsvp->getAvailableEmail();
                    }
                }
            } elseif ('ticket' == $purpose && $event) {
                $tickets = $event->getTickets();
                foreach ($tickets as $ticket) {
                    if (
                        str_starts_with(
                            (string) $ticket->getStatus(),
                            'paid'
                        )
                        || 'reserved' == $ticket->getStatus()
                    ) {
                        $to = $ticket->getOwnerEmail();
                        if (null == $to) {
                            $to = $ticket->getEmail();
                        }
                        if (!in_array($to, $emails)) {
                            $emails[] = $to;
                        }
                    }
                }
            } elseif ('nakkikone' == $purpose && $event) {
                $nakkis = $event->getNakkiBookings();
                foreach ($nakkis as $nakki) {
                    $member = $nakki->getMember();
                    if ($member) {
                        $emails[$member->getId()] = $member->getEmail();
                        $locales[$member->getId()] =
                            $member->getLocale() ?? 'fi';
                    }
                }
            } elseif ('artist' == $purpose && $event) {
                $signups = $event->getEventArtistInfos();
                foreach ($signups as $signup) {
                    $artist = $signup->getArtist();
                    if ($artist) {
                        $member = $signup->getArtist()->getMember();
                        if ($member) {
                            $emails[$member->getId()] = $member->getEmail();
                            $locales[$member->getId()] =
                                $member->getLocale() ?? 'fi';
                        }
                    } else {
                        $this->addFlash(
                            'sonata_flash_error',
                            sprintf(
                                'Artist %s member not found.',
                                $signup->getArtistClone()->getName()
                            )
                        );
                    }
                }
            } elseif ('aktiivit' == $purpose) {
                foreach (
                    $memberRepository->findBy([
                        'isActiveMember' => true,
                        'emailVerified' => true,
                        'allowActiveMemberMails' => true,
                    ]) as $member
                ) {
                    $emails[$member->getId()] = $member->getEmail();
                    $locales[$member->getId()] = $member->getLocale() ?? 'fi';
                }
            } elseif ('tiedotus' == $purpose) {
                foreach (
                    $memberRepository->findBy([
                        'emailVerified' => true,
                        'allowInfoMails' => true,
                    ]) as $member
                ) {
                    $emails[$member->getId()] = $member->getEmail();
                    $locales[$member->getId()] = $member->getLocale() ?? 'fi';
                }
            } elseif ('vj_roster' == $purpose) {
                $emails = $this->getRoster('VJ', $aRepo);
            } elseif ('dj_roster' == $purpose) {
                $emails = $this->getRoster('DJ', $aRepo);
            } else {
                $this->addFlash(
                    'sonata_flash_error',
                    sprintf('Purpose %s not supported.', $purpose)
                );
            }
        }
        // Initialize progress
        $totalEmails = count($emails);
        $session->set('email_send_progress', [
            'current' => 0,
            'total' => $totalEmails,
            'completed' => false,
        ]);

        if ($this->requestStack->getCurrentRequest()->isXmlHttpRequest()) {
            // Update session at the beginning to indicate process has started
            $session->set('email_send_progress', [
                'current' => 0,
                'total' => $totalEmails,
                'completed' => false,
            ]);

            $count = 0;
            foreach ($emails as $id => $to) {
                if ($to) {
                    $locale = $locales[$id] ?? 'fi';
                    $message = $this->generateMail(
                        $to,
                        $replyto,
                        $subject,
                        $body,
                        $links,
                        $img,
                        $locale
                    );
                    $mailer->send($message);
                    ++$count;

                    // Update progress after each email is sent
                    $session->set('email_send_progress', [
                        'current' => $count,
                        'total' => $totalEmails,
                        'completed' => $count >= $totalEmails,
                        'redirectUrl' => $this->admin->generateUrl(
                            'list',
                            $this->admin->getFilterParameters()
                        ),
                    ]);

                    // Force session write
                    $session->save();

                    // Add a small delay to prevent overwhelming the server
                    usleep(100000); // 0.1 seconds
                }
            }

            // Update email entity
            if ($count > 0) {
                $email->setSentAt(new \DateTimeImmutable('now'));
                $user = $this->getUser();
                assert($user instanceof User);
                $email->setSentBy($user->getMember());
                $this->admin->update($email);
                $this->addFlash(
                    'sonata_flash_success',
                    sprintf('%s %s info packages sent.', $count, $purpose)
                );
            }

            return new JsonResponse([
                'success' => true,
                'count' => $count,
                'redirectUrl' => $this->admin->generateUrl(
                    'list',
                    $this->admin->getFilterParameters()
                ),
            ]);
        }

        return new RedirectResponse(
            $this->admin->generateUrl(
                'list',
                $this->admin->getFilterParameters()
            )
        );
    }

    private function generateMail(
        Address|string $to,
        Address|string $replyto,
        string $subject,
        $body,
        $links,
        $img,
        $locale = 'fi',
    ): TemplatedEmail {
        return new TemplatedEmail()
            ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
            ->to($to)
            ->replyTo($replyto)
            ->subject('[Entropy] '.$subject)
            ->htmlTemplate('emails/email.html.twig')
            ->context([
                'body' => $body,
                'links' => $links,
                'img' => $img,
                'locale' => $locale,
            ]);
    }

    private function getRoster(
        string $type,
        ArtistRepository $artistRepository,
    ): array {
        $emails = [];
        $artists = $artistRepository->findBy([
            'type' => $type,
            'copyForArchive' => false,
        ]);
        foreach ($artists as $artist) {
            if ($artist->getMember()) {
                $emails[$artist->getMember()->getId()] = $artist
                    ->getMember()
                    ->getEmail();
            }
        }

        return $emails;
    }
}
