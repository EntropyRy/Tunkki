<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Artist;
use App\Entity\Member;
use App\Entity\Sonata\SonataMediaMedia;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

/**
 * Provides a dedicated Artist entity linked to an existing Member created by UserFixtures.
 *
 * Adjustments:
 *  - Extend Doctrine FixturesBundle Fixture so auto-registration works (no manual service tag needed).
 *  - Idempotent: reuse an existing artist if present.
 *  - Register media & artist references for downstream fixtures/tests.
 *  - Enforce uppercase 'DJ' as requested.
 *
 * Static analysis cleanups:
 *  - Removed always-true instanceof inside iteration (collection already typed).
 *  - Removed redundant negated hasReference() check (earlier guard assures it's false).
 */
final class ArtistFixtures extends Fixture implements DependentFixtureInterface
{
    public const string ARTIST_REFERENCE = 'fixture_artist_for_user';
    public const string ARTIST_MEDIA_REFERENCE = 'fixture_artist_media';

    public function load(ObjectManager $manager): void
    {
        // Abort if reference already registered (idempotent run).
        if ($this->hasReference(self::ARTIST_REFERENCE, Artist::class)) {
            return;
        }

        /** @var User|null $user */
        $user = $this->hasReference(UserFixtures::USER_REFERENCE, User::class)
            ? $this->getReference(UserFixtures::USER_REFERENCE, User::class)
            : $manager->getRepository(User::class)->findOneBy(['authId' => 'local-user']);

        if (!$user instanceof User) {
            return;
        }

        $member = $user->getMember();
        if (!$member instanceof Member) {
            return;
        }

        // Attempt to reuse an existing artist (first one found).
        $existingArtist = null;
        foreach ($member->getArtist() as $artistCandidate) {
            $existingArtist = $artistCandidate; // Collection already guarantees type Artist
            break;
        }

        if ($existingArtist !== null) {
            $this->addReference(self::ARTIST_REFERENCE, $existingArtist);
            $picture = $existingArtist->getPicture();
            if ($picture instanceof SonataMediaMedia) {
                $this->addReference(self::ARTIST_MEDIA_REFERENCE, $picture);
            }
            return;
        }

        // Create media (simple in-memory object, minimal required fields).
        $media = new SonataMediaMedia();
        $media->setProviderName('sonata.media.provider.image');
        $media->setContext('artist');
        $media->setName('fixture-artist-image');
        $media->setEnabled(true);
        $media->setProviderStatus(1);
        $media->setProviderReference('fixture-artist-image-ref');
        $manager->persist($media);

        // Create artist.
        $artist = new Artist();
        $artist->setName('Fixture Artist');
        $artist->setType('DJ');
        $artist->setMember($member);
        $artist->setPicture($media);

        $manager->persist($artist);
        $manager->flush();

        $this->addReference(self::ARTIST_MEDIA_REFERENCE, $media);
        $this->addReference(self::ARTIST_REFERENCE, $artist);
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
