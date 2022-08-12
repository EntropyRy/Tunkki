<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use App\Entity\Artist;
use Symfony\Component\HttpFoundation\Response;

//use Symfony\Component\Security\Core\Security;
//use Symfony\Component\Form\FormFactoryInterface;
//use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
//use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
//use Symfony\Contracts\Translation\TranslatorInterface;

class PublicArtistController extends AbstractController
{
    public function index(Artist $artist, Request $request): Response
    {
        return $this->render('artist/one.html.twig', [
            'artist' => $artist,
        ]);
    }
}
