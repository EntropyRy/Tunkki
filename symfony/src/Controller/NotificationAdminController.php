<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Message\SendTelegramNotification;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @extends CRUDController<Notification>
 */
final class NotificationAdminController extends CRUDController
{
    public function sendAction(
        MessageBusInterface $messageBus,
    ): RedirectResponse {
        $notification = $this->admin->getSubject();

        if (!$notification instanceof Notification) {
            $this->addFlash('error', 'Invalid notification');

            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }

        try {
            $messageBus->dispatch(new SendTelegramNotification($notification->getId()));
            $this->addFlash('success', 'Message sent');
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Message NOT sent: '.$e->getMessage());
        }

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
