<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Artist;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicArtistController extends AbstractController
{
  #[Route(
    [
      'fi' =>   '/artisti/{id}/{name}',
      'en' =>   '/artist/{id}/{name}',
    ],
    name: 'entropy_public_artist',
  )]
  public function index(Artist $artist): Response
  {
    return $this->render('artist/one.html.twig', [
      'artist' => $artist,
    ]);
  }
}
