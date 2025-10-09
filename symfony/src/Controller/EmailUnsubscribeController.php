<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EmailUnsubscribeController extends AbstractController
{
    #[Route([
        'en' => '/email/{code}/unsubscribe',
        'fi' => '/email/{code}/peruuta',
    ], name: 'app_email_unsubscribe')]
    public function index(
        #[MapEntity(mapping: ['code' => 'code'])]
        Member $member,
    ): Response {
        return $this->render('email_unsubscribe/index.html.twig', [
            'email' => $member->getEmail(),
        ]);
    }
}
