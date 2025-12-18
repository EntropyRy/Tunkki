<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Email;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailPurpose;
use App\Service\Email\EmailService;
use App\Service\QrService;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends CRUDController<Email>
 */
final class EmailAdminController extends CRUDController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly QrService $qr,
    ) {
    }

    #[\Override]
    public function editAction(Request $request): Response
    {
        // If editing from standalone admin and email has an event, redirect to child admin
        if (!$this->admin->isChild()) {
            $email = $this->admin->getSubject();
            \assert($email instanceof Email);

            if ($email->getEvent() instanceof Event) {
                // Redirect to the event child admin edit route
                $eventId = $email->getEvent()->getId();
                $emailId = $email->getId();

                return $this->redirectToRoute('admin_app_event_email_edit', [
                    'id' => $eventId,
                    'childId' => $emailId,
                ]);
            }
        }

        // Otherwise, proceed with normal edit action
        return parent::editAction($request);
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
        if (null !== $event) {
            $img = $event->getPicture();
            if (EmailPurpose::TICKET_QR === $email->getPurpose()) {
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
        EmailService $emailService,
    ): RedirectResponse|JsonResponse {
        $session = $this->requestStack->getSession();
        $email = $this->admin->getSubject();
        $purpose = $email->getPurpose();

        if (!$purpose) {
            $this->addFlash('sonata_flash_error', 'Email purpose not set.');

            return new RedirectResponse(
                $this->admin->generateUrl(
                    'list',
                    $this->admin->getFilterParameters()
                )
            );
        }

        // Initialize progress
        $session->set('email_send_progress', [
            'current' => 0,
            'total' => 0,
            'completed' => false,
        ]);

        if ($this->requestStack->getCurrentRequest()->isXmlHttpRequest()) {
            try {
                $user = $this->getUser();
                \assert($user instanceof User);

                $result = $emailService->send(
                    $email,
                    function (int $current, int $total) use ($session): void {
                        $session->set('email_send_progress', [
                            'current' => $current,
                            'total' => $total,
                            'completed' => $current >= $total,
                            'redirectUrl' => $this->admin->generateUrl(
                                'list',
                                $this->admin->getFilterParameters()
                            ),
                        ]);
                        $session->save();
                        usleep(100000); // 0.1 seconds
                    },
                    $user->getMember()
                );

                $this->addFlash(
                    'sonata_flash_success',
                    \sprintf(
                        '%d emails sent for purpose "%s".',
                        $result->totalSent,
                        $purpose->value
                    )
                );

                if ($result->getFailureCount() > 0) {
                    $this->addFlash(
                        'sonata_flash_warning',
                        \sprintf('%d emails failed to send.', $result->getFailureCount())
                    );
                }

                return new JsonResponse([
                    'success' => true,
                    'count' => $result->totalSent,
                    'redirectUrl' => $this->admin->generateUrl(
                        'list',
                        $this->admin->getFilterParameters()
                    ),
                ]);
            } catch (\Exception $e) {
                $this->addFlash(
                    'sonata_flash_error',
                    \sprintf('Error: %s', $e->getMessage())
                );

                return new JsonResponse([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'redirectUrl' => $this->admin->generateUrl(
                        'list',
                        $this->admin->getFilterParameters()
                    ),
                ]);
            }
        }

        return new RedirectResponse(
            $this->admin->generateUrl(
                'list',
                $this->admin->getFilterParameters()
            )
        );
    }
}
