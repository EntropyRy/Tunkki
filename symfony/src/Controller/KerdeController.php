<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DoorLog;
use App\Entity\User;
use App\Form\OpenDoorType;
use App\Repository\DoorLogRepository;
use App\Service\BarcodeService;
use App\Service\MattermostNotifierService;
use App\Service\SSHService;
use App\Service\ZMQService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class KerdeController extends AbstractController
{
    #[
        Route(
            path: ['en' => '/kerde/door', 'fi' => '/kerde/ovi'],
            name: 'kerde_door',
        ),
    ]
    public function door(
        Request $request,
        FormFactoryInterface $formF,
        MattermostNotifierService $mm,
        ZMQService $zmq,
        BarcodeService $barcodeService,
        EntityManagerInterface $em,
        DoorLogRepository $doorlogrepo,
        SSHService $ssh,
    ): RedirectResponse|Response {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();

        $DoorLog = new DoorLog();
        $DoorLog->setMember($member);
        $since = new \DateTimeImmutable('now-1day');
        if ($request->query->has('since')) {
            // $datestring = strtotime($request->query->get('since'));
            $since = new \DateTimeImmutable($request->query->getString('since'));
        }
        $logs = $doorlogrepo->getSince($since);
        $form = $formF->create(OpenDoorType::class, $DoorLog);
        $now = new \DateTimeImmutable('now');
        $status = $zmq->sendInit(
            $member->getUsername(),
            $now->getTimestamp(),
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $doorlog = $form->getData();
            $em->persist($doorlog);
            $em->flush();
            $status = $zmq->sendOpen(
                $member->getUsername(),
                $now->getTimestamp(),
            );
            // $this->addFlash('success', 'profile.door.opened');
            $this->addFlash('success', $status);

            $send = true;
            $text = '**Kerde door opened by '.$doorlog->getMember();
            if ($doorlog->getMessage()) {
                $text .= ' - '.$doorlog->getMessage();
            } else {
                foreach ($logs as $log) {
                    if (
                        !$log->getMessage()
                        && $now->getTimestamp() -
                            $log->getCreatedAt()->getTimestamp() <
                            60 * 60 * 4
                    ) {
                        $send = false;
                        break;
                    }
                }
            }
            $text .= '**';
            if ($send) {
                $mm->sendToMattermost($text, 'kerde');
            }

            return $this->redirectToRoute('kerde_door');
        }
        $barcode = $barcodeService->getBarcodeForCode($member->getCode());

        // $status = $ssh->checkStatus();
        // if ($status == 1) {
        //     $this->addFlash('success', 'Stream is on!');
        // }
        return $this->render('kerde/door.html.twig', [
            'form' => $form,
            'logs' => $logs,
            'member' => $member,
            'status' => $status,
            'barcode' => $barcode,
        ]);
    }

    #[Route('/kerde/recording/start', name: 'recording_start')]
    public function recordingStart(SSHService $ssh): RedirectResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if ($member->getIsActiveMember()) {
            $err = $ssh->sendCommand('start');
            if ($err) {
                $this->addFlash('warning', 'Error: '.$err);
            } else {
                $this->addFlash('success', 'stream.command.successful');
            }
        }

        return $this->redirectToRoute('kerde_door');
    }

    #[Route('/kerde/recording/stop', name: 'recording_stop')]
    public function recordingStop(SSHService $ssh): RedirectResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        if ($member->getIsActiveMember()) {
            $err = $ssh->sendCommand('stop');
            if ($err) {
                $this->addFlash('warning', 'Error: '.$err);
            } else {
                $this->addFlash('success', 'stream.command.successful');
            }
        }

        return $this->redirectToRoute('kerde_door');
    }

    #[Route('/kerde/barcodes', name: 'kerde_barcodes')]
    public function index(BarcodeService $barcodeService): Response
    {
        $barcodes = [];
        $user = $this->getUser();
        \assert($user instanceof User);
        $member = $user->getMember();
        $code = $barcodeService->getBarcodeForCode($member->getCode());
        $barcodes['Your Code'] = $code[1];
        $barcodes['10€'] = $barcodeService->getBarcodeForCode('_10e_')[1];
        $barcodes['20€'] = $barcodeService->getBarcodeForCode('_20e_')[1];
        $barcodes['Cancel'] = $barcodeService->getBarcodeForCode('_CANCEL_')[1];
        $barcodes['Manual'] = $barcodeService->getBarcodeForCode('1812271001')[1];

        // $barcodes['Statistics'] = $generator->getBarcode('0348030005', $generator::TYPE_CODE_128, 2, 90);
        return $this->render('kerde/barcodes.html.twig', [
            'barcodes' => $barcodes,
        ]);
    }
}
