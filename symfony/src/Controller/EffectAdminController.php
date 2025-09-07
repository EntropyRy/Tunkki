<?php

declare(strict_types=1);

namespace App\Controller;

use App\Effect\BackgroundEffectConfigProvider;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SONATA_ADMIN')]
final class EffectAdminController extends AbstractController
{
    #[Route(
        path: '/admin/effects/flowfields/{id}',
        name: 'admin_effects_flowfields',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function flowfieldsDashboard(
        Event $event,
        BackgroundEffectConfigProvider $provider,
        Request $request
    ): Response {
        // Prepare JSON config for UI: merge existing with defaults, pretty-print
        $currentJson = $event->getBackgroundEffectConfig();
        $configJson = $provider->normalizeJson(
            $currentJson,
            'flowfields',
            true
        );

        // CSRF token for save action
        $csrfId = $this->csrfIdForEvent($event->getId());
        $csrfToken = $this->container->get('security.csrf.token_manager')->getToken($csrfId)->getValue();

        return $this->render('admin/effects/flowfields_dashboard.html.twig', [
            'event' => $event,
            'configJson' => $configJson,
            'saveUrl' => $this->generateUrl('admin_effects_flowfields_save', ['id' => $event->getId()]),
            'csrf_token' => $csrfToken,
        ]);
    }

    #[Route(
        path: '/admin/effects/flowfields/{id}/save',
        name: 'admin_effects_flowfields_save',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    public function saveFlowfieldsConfig(
        Event $event,
        Request $request,
        BackgroundEffectConfigProvider $provider,
        EntityManagerInterface $em
    ): Response {
        $submittedToken = (string) $request->request->get('_token', '');
        $csrfId = $this->csrfIdForEvent($event->getId());
        if (!$this->isCsrfTokenValid($csrfId, $submittedToken)) {
            $this->addFlash('danger', 'Security token is invalid. Please retry.');
            return $this->redirectToRoute('admin_effects_flowfields', ['id' => $event->getId()]);
        }

        $raw = $request->request->get('config');
        $raw = is_string($raw) ? $raw : '';

        try {
            // Validate JSON and pretty print merged with defaults
            $parsed = $provider->parseJson($raw, true) ?? [];
            $merged = $provider->mergeWithDefaults('flowfields', $parsed);
            $normalized = $provider->toJson($merged, true);

            // Persist
            $event->setBackgroundEffectConfig($normalized);
            $em->flush();

            $this->addFlash('success', 'Flowfields configuration saved.');
        } catch (\JsonException $e) {
            $this->addFlash('danger', 'Invalid JSON: ' . $e->getMessage());
        }

        // Redirect back to dashboard
        return $this->redirectToRoute('admin_effects_flowfields', ['id' => $event->getId()]);
    }

    private function csrfIdForEvent(int $eventId): string
    {
        return 'flowfields_config_' . $eventId;
    }
}
