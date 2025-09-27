<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Sonata\SonataPageSite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Provides a minimal pair of SonataPageSite entities for functional tests
 * and local development:
 *   - FI default site at "/"
 *   - EN site at "/en"
 *
 * Tests currently duplicate on-the-fly site creation logic. Introducing this
 * fixture allows those tests to be simplified so they only need to rely on
 * database fixtures instead of imperative setup code.
 *
 * You can load this along with other fixtures (e.g. UserFixtures, EventFixtures)
 * via:
 *   php bin/console doctrine:fixtures:load --group=test
 *
 * If you adopt grouping, you can add a getGroups() method (from
 * Doctrine\Bundle\FixturesBundle\FixtureGroupInterface) later. For now this
 * is a plain fixture.
 */
final class SiteFixtures extends Fixture
{
    public const REFERENCE_DEFAULT_FI = 'site_fi_default';
    public const REFERENCE_EN = 'site_en';

    public function load(ObjectManager $manager): void
    {
        // FI default site
        $fi = new SonataPageSite();
        $fi->setName('FI');
        $fi->setEnabled(true);
        $fi->setHost('localhost');
        $fi->setRelativePath('/');
        $fi->setLocale('fi');
        $fi->setIsDefault(true);
        $fi->setEnabledFrom(new \DateTimeImmutable('-1 day'));
        $fi->setEnabledTo(null);
        $manager->persist($fi);
        $this->addReference(self::REFERENCE_DEFAULT_FI, $fi);

        // EN site
        $en = new SonataPageSite();
        $en->setName('EN');
        $en->setEnabled(true);
        $en->setHost('localhost');
        $en->setRelativePath('/en');
        $en->setLocale('en');
        $en->setIsDefault(false);
        $en->setEnabledFrom(new \DateTimeImmutable('-1 day'));
        $en->setEnabledTo(null);
        $manager->persist($en);
        $this->addReference(self::REFERENCE_EN, $en);

        $manager->flush();
    }
}
