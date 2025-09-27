<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Artist;
use App\Entity\Member;
use App\Entity\Sonata\SonataMediaMedia;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Provides a dedicated Artist entity linked to an existing Member created
 * by UserFixtures. Also creates a minimal SonataMediaMedia record to act
 * as the Artist picture so tests exercising picture-dependent logic
 * (e.g. ArtistController::create validation) can rely on a consistent
 * pre-populated state.
 *
 * Load order:
 *  - Expects UserFixtures to have already created the base normal user
 *    referenced by UserFixtures::USER_REFERENCE.
 *
 * References added:
 *  - ArtistFixtures::ARTIST_REFERENCE
 *  - ArtistFixtures::ARTIST_MEDIA_REFERENCE
 */
final class ArtistFixtures extends AbstractFixture
{
    public const ARTIST_REFERENCE = "fixture_artist_for_user";
    public const ARTIST_MEDIA_REFERENCE = "fixture_artist_media";

    public function load(ObjectManager $manager): void
    {
        // Resolve the primary user/member from UserFixtures.
        $member = null;
        if (
            !$this->hasReference(
                \App\DataFixtures\UserFixtures::USER_REFERENCE,
                \App\Entity\User::class,
            )
        ) {
            return;
        }
        /** @var \App\Entity\User $user */
        $user = $this->getReference(
            \App\DataFixtures\UserFixtures::USER_REFERENCE,
            \App\Entity\User::class,
        );
        $member = $user->getMember();

        if (!$member instanceof Member) {
            return;
        }

        // Create a minimal media object to attach as picture.
        $media = new SonataMediaMedia();
        // BaseMedia requires at least: providerName, context, name, and enabled.
        $media->setProviderName("sonata.media.provider.image");
        $media->setContext("artist");
        $media->setName("fixture-artist-image");
        $media->setEnabled(true);
        // Optional: set dummy reference fields to satisfy potential listeners.
        $media->setProviderStatus(1);
        $media->setProviderReference("fixture-artist-image-ref");

        $manager->persist($media);

        $artist = new Artist();
        $artist->setName("Fixture Artist");
        $artist->setType("dj");
        $artist->setMember($member);
        $artist->setPicture($media);

        $manager->persist($artist);
        $manager->flush();

        $this->addReference(self::ARTIST_MEDIA_REFERENCE, $media);
        $this->addReference(self::ARTIST_REFERENCE, $artist);
    }
}
