<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\Happening;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Test fixture providing:
 *  - A published event that allows members to create happenings.
 *  - A public (released) happening under that event.
 *  - A second event with an unreleased (non-public) happening.
 *
 * References exposed for tests:
 *  - EVENT_PUBLIC_REFERENCE
 *  - EVENT_PRIVATE_REFERENCE
 *  - HAPPENING_PUBLIC_REFERENCE
 *  - HAPPENING_PRIVATE_REFERENCE
 *
 * Static analysis adjustments:
 *  - Removed redundant method_exists checks (method is defined on Event).
 *  - Removed incorrect @var User|null docblock causing native type mismatch.
 */
final class HappeningTestFixtures extends Fixture implements DependentFixtureInterface
{
    public const string EVENT_PUBLIC_REFERENCE = 'fixture_event_happening_public';
    public const string EVENT_PRIVATE_REFERENCE = 'fixture_event_happening_private';
    public const string HAPPENING_PUBLIC_REFERENCE = 'fixture_happening_public';
    public const string HAPPENING_PRIVATE_REFERENCE = 'fixture_happening_private';

    public function load(ObjectManager $manager): void
    {
        /** @var User $user */
        $user = $this->getReference(UserFixtures::USER_REFERENCE, User::class);

        $ownerMember = $user->getMember();
        if (!$ownerMember instanceof Member) {
            return;
        }

        // 1. Public Event (allows member happenings)
        $eventPublic = new Event()
            ->setName('Happening Enabled Event (EN)')
            ->setNimi('Tapahtuma jossa happeningit (FI)')
            ->setType('party')
            ->setEventDate(new \DateTimeImmutable('+5 days'))
            ->setPublishDate(new \DateTimeImmutable('-1 day'))
            ->setUrl('happening-event')
            ->setPublished(true)
            ->setAllowMembersToCreateHappenings(true);

        $manager->persist($eventPublic);
        $this->addReference(self::EVENT_PUBLIC_REFERENCE, $eventPublic);

        // 2. Private Event (members cannot create happenings)
        $eventPrivate = new Event()
            ->setName('Secret Event (EN)')
            ->setNimi('Salainen tapahtuma (FI)')
            ->setType('internal')
            ->setEventDate(new \DateTimeImmutable('+10 days'))
            ->setPublishDate(new \DateTimeImmutable('-1 day'))
            ->setUrl('secret-event')
            ->setPublished(true)
            ->setAllowMembersToCreateHappenings(false);

        $manager->persist($eventPrivate);
        $this->addReference(self::EVENT_PRIVATE_REFERENCE, $eventPrivate);

        // 3. Public Happening (released)
        $publicHappening = new Happening()
            ->setEvent($eventPublic)
            ->addOwner($ownerMember)
            ->setNameFi('Julkinen Happeninki')
            ->setNameEn('Public Happening')
            ->setDescriptionFi('Kuvaus julkisesta happeninkistä.')
            ->setDescriptionEn('Description for the public happening.')
            ->setTime(new \DateTime('+6 days'))
            ->setType('event')
            ->setNeedsPreliminarySignUp(true)
            ->setNeedsPreliminaryPayment(false)
            ->setMaxSignUps(5)
            ->setSignUpsOpenUntil(new \DateTime('+7 days'))
            ->setSlugFi('julkinen-happeninki')
            ->setSlugEn('public-happening')
            ->setReleaseThisHappeningInEvent(true)
            ->setAllowSignUpComments(true);

        $manager->persist($publicHappening);
        $this->addReference(self::HAPPENING_PUBLIC_REFERENCE, $publicHappening);

        // 4. Private (unreleased) Happening
        $privateHappening = new Happening()
            ->setEvent($eventPrivate)
            ->addOwner($ownerMember)
            ->setNameFi('Salainen Happeninki')
            ->setNameEn('Secret Happening')
            ->setDescriptionFi('Tämä ei ole vielä julkaistu.')
            ->setDescriptionEn('This happening is not released yet.')
            ->setTime(new \DateTime('+11 days'))
            ->setType('event')
            ->setNeedsPreliminarySignUp(false)
            ->setNeedsPreliminaryPayment(false)
            ->setMaxSignUps(0)
            ->setSlugFi('salainen-happeninki')
            ->setSlugEn('secret-happening')
            ->setReleaseThisHappeningInEvent(false)
            ->setAllowSignUpComments(false);

        $manager->persist($privateHappening);
        $this->addReference(self::HAPPENING_PRIVATE_REFERENCE, $privateHappening);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
