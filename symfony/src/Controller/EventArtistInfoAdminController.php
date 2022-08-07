<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class EventArtistInfoAdminController extends CRUDController
{
    public function updateAction()
    {
        $info = $this->admin->getSubject();
        $artistClone = $info->getArtistClone();
        $artist = $info->getArtist();
        if ($artistClone) {
            $artistClone->setGenre($artist->getGenre());
            $artistClone->setType($artist->getType());
            $artistClone->setHardware($artist->getHardware());
            $artistClone->setBio($artist->getBio());
            $artistClone->setBioEn($artist->getBioEn());
            $artistClone->setPicture($artist->getPicture());
            $artistClone->setLinks($artist->getLinks());
            $this->admin->update($artistClone);
            $this->admin->update($info);
            $this->addFlash('sonata_flash_success', sprintf('%s info updated', $info->getArtist()->getName()));
        } else {
            $this->addFlash('sonata_flash_warning', 'Nothing to do!');
        }
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
