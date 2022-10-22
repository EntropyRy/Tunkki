<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\SSH;

/**
 * @IsGranted("ROLE_USER")
 */
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
