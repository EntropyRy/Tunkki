<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Helper\SSH;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class RecordingController extends AbstractController
{
    #[Route('/kerde/recording/start', name: 'app_recording_start')]
    public function start(Request $request, SSH $ssh): RedirectResponse
    {
        $member = $this->getUser()->getMember();
        if ($member->getIsActiveMember()) {
            $err = $ssh->sendCommand('start');
            if ($err) {
                $this->addFlash('warning', 'Error: '.$err);
            } else {
                $this->addFlash('success', 'stream.command.successful');
            }
        }
        return $this->redirectToRoute('entropy_profile_door.'. $request->getLocale());
    }
    #[Route('/kerde/recording/stop', name: 'app_recording_stop')]
    public function stop(Request $request, SSH $ssh): RedirectResponse
    {
        $member = $this->getUser()->getMember();
        if ($member->getIsActiveMember()) {
            $err = $ssh->sendCommand('stop');
            if ($err) {
                $this->addFlash('warning', 'Error: '.$err);
            } else {
                $this->addFlash('success', 'stream.command.successful');
            }
        }
        return $this->redirectToRoute('entropy_profile_door.'. $request->getLocale());
    }
}
