<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\Qr;
use App\Repository\ArtistRepository;
use App\Repository\MemberRepository;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class EmailAdminController extends CRUDController
{
    public function previewAction(): Response
    {
        $email = $this->admin->getSubject();
        $event = $email->getEvent();
        $img = null;
        $qr = null;
        if (!is_null($event)) {
            $img = $event->getPicture();
            if ($email->getPurpose() == 'ticket_qr') {
                $qrGenerator = new Qr();
                $qr = $qrGenerator->getQrBase64("test");
            }
        }
        $admin = $this->admin;
        return $this->render('emails/admin_preview.html.twig', [
            'body' => $email->getBody(),
            'qr' => $qr,
            'email' => $email,
            'admin' => $admin,
            'img' => $img
        ]);
    }
    public function sendAction(MailerInterface $mailer, ArtistRepository $aRepo, MemberRepository $memberRepository): RedirectResponse
    {
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
            if ($purpose == 'rsvp' && $event) {
                $rsvps = $event->getRSVPs();
                if (count($rsvps) > 0) {
                    foreach ($rsvps as $rsvp) {
                        $emails[] = $rsvp->getAvailableEmail();
                    }
                }
            } elseif ($purpose == 'ticket' && $event) {
                $tickets = $event->getTickets();
                foreach ($tickets as $ticket) {
                    if (str_starts_with((string) $ticket->getStatus(), 'paid') || $ticket->getStatus() == 'reserved') {
                        $to = $ticket->getOwnerEmail();
                        if ($to == null) {
                            $to = $ticket->getEmail();
                        }
                        if (!in_array($to, $emails)) {
                            $emails[] = $to;
                        }
                    }
                }
            } elseif ($purpose == 'nakkikone' && $event) {
                $nakkis = $event->getNakkiBookings();
                foreach ($nakkis as $nakki) {
                    $member = $nakki->getMember();
                    if ($member) {
                        $emails[$member->getId()] = $member->getEmail();
                        $locales[$member->getId()] = $member->getLocale() ?? 'fi';
                    }
                }
            } elseif ($purpose == 'artist' && $event) {
                $signups = $event->getEventArtistInfos();
                foreach ($signups as $signup) {
                    $artist = $signup->getArtist();
                    if ($artist) {
                        $member = $signup->getArtist()->getMember();
                        if ($member) {
                            $emails[$member->getId()] = $member->getEmail();
                            $locales[$member->getId()] = $member->getLocale() ?? 'fi';
                        }
                    } else {
                        $this->addFlash('sonata_flash_error', sprintf('Artist %s member not found.', $signup->getArtistClone()->getName()));
                    }
                }
            } elseif ($purpose == 'aktiivit') {
                foreach ($memberRepository->findBy(['isActiveMember' => true, 'emailVerified' => true, 'allowActiveMemberMails' => true]) as $member) {
                    $emails[$member->getId()] = $member->getEmail();
                    $locales[$member->getId()] = $member->getLocale() ?? 'fi';
                }
            } elseif ($purpose == 'tiedotus') {
                foreach ($memberRepository->findBy(['emailVerified' => true, 'allowInfoMails' => true]) as $member) {
                    $emails[$member->getId()] = $member->getEmail();
                    $locales[$member->getId()] = $member->getLocale() ?? 'fi';
                }
            } elseif ($purpose == 'vj_roster') {
                $emails = $this->getRoster('VJ', $aRepo);
            } elseif ($purpose == 'dj_roster') {
                $emails = $this->getRoster('DJ', $aRepo);
            } else {
                $this->addFlash('sonata_flash_error', sprintf('Purpose %s not supported.', $purpose));
            }
        }
        foreach ($emails as $id => $to) {
            if ($to) {
                $locale = $locales[$id] ?? 'fi';
                $message = $this->generateMail($to, $replyto, $subject, $body, $links, $img, $locale);
                $mailer->send($message);
                $count += 1;
            }
        }
        if ($count > 0) {
            $email->setSentAt(new \DateTimeImmutable('now'));
            $this->admin->update($email);
            $this->addFlash('sonata_flash_success', sprintf('%s %s info packages sent.', $count, $purpose));
        }
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
    private function generateMail($to, $replyto, $subject, $body, $links, $img, $locale = 'fi'): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
            ->to($to)
            ->replyTo($replyto)
            ->subject($subject)
            ->htmlTemplate('emails/email.html.twig')
            ->context(['body' => $body, 'links' => $links, 'img' => $img, 'locale' => $locale]);
    }
    private function getRoster(string $type, ArtistRepository $artistRepository): array
    {
        $emails = [];
        $artists = $artistRepository->findBy(['type' => $type, 'copyForArchive' => false]);
        foreach ($artists as $artist) {
            if ($artist->getMember()) {
                $emails[$artist->getMember()->getId()] = $artist->getMember()->getEmail();
            }
        }
        return $emails;
    }
}
