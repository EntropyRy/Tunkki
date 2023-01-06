<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use App\Entity\Artist;
use Symfony\Component\HttpFoundation\Response;

class PublicArtistController extends AbstractController
{
    public function index(Artist $artist): Response
    {
        return $this->render('artist/one.html.twig', [
            'artist' => $artist,
        ]);
    }
}
