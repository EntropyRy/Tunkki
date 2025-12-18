<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Enum\EmailPurpose;
use App\Service\Email\EmailService;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends CRUDController<Member>
 */
final class MemberAdminController extends CRUDController
{
    public function activememberinfoAction(
        EmailService $emailService,
    ): RedirectResponse {
        $subject = $this->admin->getSubject();

        try {
            $emailService->sendToRecipient(
                EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE,
                $subject->getEmail(),
                null, // No event context
                $subject->getLocale() ?? 'fi'
            );

            $this->addFlash(
                'sonata_flash_success',
                \sprintf(
                    'Member info package sent to %s',
                    (string) $subject->getName(),
                ),
            );
        } catch (\RuntimeException $e) {
            $this->addFlash(
                'sonata_flash_error',
                \sprintf('Error sending email: %s', $e->getMessage()),
            );
        }

        $url = $this->admin->generateUrl(
            'list',
            $this->admin->getFilterParameters(),
        );

        return new RedirectResponse($url);
    }
}
