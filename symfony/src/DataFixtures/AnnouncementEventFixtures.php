<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Provides a single published "announcement" Event used by the FrontPage page service.
 *
 * Context:
 *  FrontPage::execute() calls:
 *      $announcement = $eventRepository->findOneEventByType('announcement');
 *  and later merges it into the events array.
 *
 *  The repository method findOneEventByType('announcement') matches exactly the lowercase
 *  string 'announcement':
 *
 *      ->andWhere('c.type = :val') with :val = 'announcement'
 *
 *  BUT getFutureEvents() excludes only 'Announcement' (capital A):
 *
 *      ->andWhere('e.type != :type') with :type = 'Announcement'
 *
 *  Because of this case difference, if you create a lowercase 'announcement' event it will
 *  ALSO appear in the "future events" list (i.e. duplicated on the front page).
 *
 *  Short-term solution:
 *    - Populate a lowercase 'announcement' event so the FrontPage always has a non-null announcement.
 *    - (Optional) Later harmonize repository logic to use consistent casing or LOWER() comparisons.
 *
 *  If/when you normalize casing in the repository, adjust or regenerate this fixture accordingly.
 */
final class AnnouncementEventFixtures extends Fixture
{
    public const string REFERENCE_ANNOUNCEMENT = 'fixture_event_announcement';

    public function load(ObjectManager $manager): void
    {
        // Avoid creating duplicates if an announcement already exists (same type + slug).
        $existing = $manager->getRepository(Event::class)->findOneBy([
            'type' => 'announcement',
            'url' => 'announcement',
        ]);

        if ($existing instanceof Event) {
            // Ensure it's published and in the near future so it surfaces.
            if (!$existing->isPublished()) {
                $existing->setPublished(true);
            }
            if ($existing->getPublishDate() > new \DateTime()) {
                $existing->setPublishDate(new \DateTimeImmutable('-10 minutes'));
            }
            // Keep or adjust event date minimally (only if in the past).
            if ($existing->getEventDate() < new \DateTime()) {
                $existing->setEventDate(new \DateTimeImmutable('+7 days'));
            }
            $manager->persist($existing);
            $manager->flush();
            $this->addReference(self::REFERENCE_ANNOUNCEMENT, $existing);

            return;
        }

        $announcement = new Event();
        $announcement->setName('Important Announcement');
        $announcement->setNimi('Tärkeä Ilmoitus');
        // Critical: lowercase to match current repository query.
        $announcement->setType('announcement');
        $announcement->setPublished(true);
        $announcement->setPublishDate(new \DateTimeImmutable('-15 minutes'));
        // Event date a few days ahead so it still appears among future events.
        $announcement->setEventDate(new \DateTimeImmutable('+5 days')->setTime(12, 0));
        $announcement->setUrl('announcement');
        $announcement->setTemplate('event.html.twig');
        $announcement->setContent('<p>EN: Fixture announcement content.</p>');
        $announcement->setSisallys('<p>FI: Testi-ilmoituksen sisältö.</p>');

        $manager->persist($announcement);
        $manager->flush();

        $this->addReference(self::REFERENCE_ANNOUNCEMENT, $announcement);
    }
}
