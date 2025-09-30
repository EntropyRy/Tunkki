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
 */
final class ArtistFixtures extends Fixture implements DependentFixtureInterface
{
    public const string ARTIST_REFERENCE = 'fixture_artist_for_user';
    public const string ARTIST_MEDIA_REFERENCE = 'fixture_artist_media';

    public function load(ObjectManager $manager): void
    {
        // Already created?
        if ($this->hasReference(self::ARTIST_REFERENCE, Artist::class)) {
            return;
        }

        // Resolve base user (reference first, fallback to repository).
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

        // Reuse existing artist if present.
        $existingArtist = null;
        $artists = $member->getArtist(); // Collection<Artist>
        foreach ($artists as $artistCandidate) {
            if ($artistCandidate instanceof Artist) {
                $existingArtist = $artistCandidate;
                break;
            }
        }

        if ($existingArtist instanceof Artist) {
            if (!$this->hasReference(self::ARTIST_REFERENCE, Artist::class)) {
                $this->addReference(self::ARTIST_REFERENCE, $existingArtist);
            }
            $picture = $existingArtist->getPicture();
            if ($picture instanceof SonataMediaMedia && !$this->hasReference(self::ARTIST_MEDIA_REFERENCE, SonataMediaMedia::class)) {
                $this->addReference(self::ARTIST_MEDIA_REFERENCE, $picture);
            }
            return;
        }

        // Create media.
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
