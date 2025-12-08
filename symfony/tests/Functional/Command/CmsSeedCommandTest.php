<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\CmsSeedCommand;
use App\Entity\Sonata\SonataPagePage;
use App\Entity\Sonata\SonataPageSite;
use App\Tests\_Base\FixturesWebTestCase;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension;
use Sonata\PageBundle\Service\Contract\CreateSnapshotBySiteInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional tests for the entropy:cms:seed console command (CmsSeedCommand).
 *
 * Focus:
 *  - Command successfully seeds CMS baseline via CmsBaselineStory
 *  - Idempotent execution (running twice doesn't duplicate)
 *  - Output messages indicate success and summary
 *  - Advisory lock prevents race conditions (tested via serial execution)
 *
 * We test:
 *  - Initial seed creates expected Sites and Pages
 *  - Re-running command is safe (no duplication)
 *  - Command output includes expected success messages
 *  - Database state after seeding matches CmsBaselineStory contract
 */
final class CmsSeedCommandTest extends FixturesWebTestCase
{
    private function makeCommandTester(): CommandTester
    {
        $kernel = static::bootKernel();
        $application = new Application();

        $container = static::getContainer();
        $command = new CmsSeedCommand(
            $container->get('doctrine.orm.entity_manager'),
            $container->get(CreateSnapshotBySiteInterface::class),
        );

        $application->addCommand($command);
        $cmd = $application->find('entropy:cms:seed');

        return new CommandTester($cmd);
    }

    public function testCommandSeedsCmsBaselineSuccessfully(): void
    {
        // Clear any existing CMS data to start fresh
        $em = $this->em();
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        // Remove all sites and pages
        foreach ($pageRepo->findAll() as $page) {
            $em->remove($page);
        }
        foreach ($siteRepo->findAll() as $site) {
            $em->remove($site);
        }
        $em->flush();

        // Verify clean slate
        $this->assertSame(0, $siteRepo->count([]), 'Sites should be empty before seeding');
        $this->assertSame(0, $pageRepo->count([]), 'Pages should be empty before seeding');

        // Execute the command
        [$tester, $exitCode] = $this->runCmsSeed();

        // Assert successful exit
        $this->assertSame(0, $exitCode, 'Command should exit successfully');

        // Assert output contains success message
        $output = $tester->getDisplay();
        $this->assertStringContainsString('CMS seed completed', $output);
        $this->assertStringContainsString('CmsBaselineStory', $output);

        // Verify database state: should have exactly 2 sites (fi and en)
        $sites = $siteRepo->findAll();
        $this->assertCount(2, $sites, 'Should have exactly 2 sites after seeding');

        // Find FI and EN sites
        $fiSite = $siteRepo->findOneBy(['locale' => 'fi']);
        $enSite = $siteRepo->findOneBy(['locale' => 'en']);

        $this->assertNotNull($fiSite, 'Finnish site should exist');
        $this->assertNotNull($enSite, 'English site should exist');

        // Verify site properties
        $this->assertTrue($fiSite->getIsDefault(), 'Finnish site should be default');
        $this->assertFalse($enSite->getIsDefault(), 'English site should not be default');
        $this->assertSame('', $fiSite->getRelativePath(), 'Finnish site should have empty relative path');
        $this->assertSame('/en', $enSite->getRelativePath(), 'English site should have /en relative path');

        // Verify root pages exist for both sites
        $fiRoot = $pageRepo->findOneBy(['site' => $fiSite, 'url' => '/']);
        $enRoot = $pageRepo->findOneBy(['site' => $enSite, 'url' => '/']);

        $this->assertNotNull($fiRoot, 'Finnish root page should exist');
        $this->assertNotNull($enRoot, 'English root page should exist');

        // Verify core pages exist (events, join, announcements, stream)
        $fiEvents = $pageRepo->findOneBy(['site' => $fiSite, 'pageAlias' => '_page_alias_events_fi']);
        $enEvents = $pageRepo->findOneBy(['site' => $enSite, 'pageAlias' => '_page_alias_events_en']);

        $this->assertNotNull($fiEvents, 'Finnish events page should exist');
        $this->assertNotNull($enEvents, 'English events page should exist');
        $this->assertSame('/tapahtumat', $fiEvents->getUrl(), 'Finnish events page should have correct URL');
        $this->assertSame('/events', $enEvents->getUrl(), 'English events page should have correct URL');

        // Verify join pages
        $fiJoin = $pageRepo->findOneBy(['site' => $fiSite, 'pageAlias' => '_page_alias_join_us_fi']);
        $enJoin = $pageRepo->findOneBy(['site' => $enSite, 'pageAlias' => '_page_alias_join_us_en']);

        $this->assertNotNull($fiJoin, 'Finnish join page should exist');
        $this->assertNotNull($enJoin, 'English join page should exist');

        // Verify stream pages
        $fiStream = $pageRepo->findOneBy(['site' => $fiSite, 'url' => '/stream']);
        $enStream = $pageRepo->findOneBy(['site' => $enSite, 'url' => '/stream']);

        $this->assertNotNull($fiStream, 'Finnish stream page should exist');
        $this->assertNotNull($enStream, 'English stream page should exist');

        // Verify minimum page count (root + events + join + announcements + stream = 5 per site = 10 total)
        $totalPages = $pageRepo->count([]);
        $this->assertGreaterThanOrEqual(10, $totalPages, 'Should have at least 10 pages after seeding');
    }

    public function testCommandIsIdempotent(): void
    {
        // Run command first time
        [$tester1, $exitCode1] = $this->runCmsSeed();
        $this->assertSame(0, $exitCode1, 'First execution should succeed');

        // Count sites and pages after first run
        $em = $this->em();
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        $sitesCount1 = $siteRepo->count([]);
        $pagesCount1 = $pageRepo->count([]);

        $this->assertSame(2, $sitesCount1, 'Should have exactly 2 sites after first run');

        // Run command second time
        [$tester2, $exitCode2] = $this->runCmsSeed();
        $this->assertSame(0, $exitCode2, 'Second execution should succeed');

        // Count after second run
        $sitesCount2 = $siteRepo->count([]);
        $pagesCount2 = $pageRepo->count([]);

        // Should not create duplicates
        $this->assertSame($sitesCount1, $sitesCount2, 'Site count should remain the same after second run');
        $this->assertSame($pagesCount1, $pagesCount2, 'Page count should remain the same after second run');

        // Output should still indicate success
        $output = $tester2->getDisplay();
        $this->assertStringContainsString('CMS seed completed', $output);
    }

    public function testCommandOutputIncludesSiteSummary(): void
    {
        [$tester, $exitCode] = $this->runCmsSeed();

        $this->assertSame(0, $exitCode, 'Command should exit successfully');

        $output = $tester->getDisplay();

        // Should mention both locales
        $this->assertStringContainsString('fi', $output, 'Output should mention Finnish locale');
        $this->assertStringContainsString('en', $output, 'Output should mention English locale');

        // Should show relative paths
        $this->assertStringContainsString('/en', $output, 'Output should show English relative path');

        // Should indicate loading CmsBaselineStory
        $this->assertStringContainsString('Loading CmsBaselineStory', $output);
    }

    public function testCommandHandlesEmptyDatabaseGracefully(): void
    {
        // This test verifies the command works even when starting from scratch
        // (Already covered in testCommandSeedsCmsBaselineSuccessfully but worth explicit test)

        $em = $this->em();
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $pageRepo = $em->getRepository(SonataPagePage::class);

        // Clear everything
        foreach ($pageRepo->findAll() as $page) {
            $em->remove($page);
        }
        foreach ($siteRepo->findAll() as $site) {
            $em->remove($site);
        }
        $em->flush();

        [$tester, $exitCode] = $this->runCmsSeed();

        $this->assertSame(0, $exitCode, 'Command should handle empty database successfully');

        // Verify baseline was created
        $this->assertSame(2, $siteRepo->count([]), 'Should create 2 sites from scratch');

        $output = $tester->getDisplay();
        $this->assertStringContainsString('CMS seed completed', $output);
    }

    public function testCommandNormalizesExistingNonStandardSites(): void
    {
        // Create a site with wrong configuration that should be normalized
        $em = $this->em();

        // Create FI site with wrong default setting
        $fiSite = new SonataPageSite();
        $fiSite->setName('FI');
        $fiSite->setLocale('fi');
        $fiSite->setHost('localhost');
        $fiSite->setIsDefault(false); // Wrong - should be true
        $fiSite->setEnabled(false); // Wrong - should be true
        $fiSite->setRelativePath('/wrong'); // Wrong - should be empty
        $fiSite->setEnabledFrom(new \DateTime());
        $em->persist($fiSite);
        $em->flush();

        [, $exitCode] = $this->runCmsSeed();

        $this->assertSame(0, $exitCode, 'Command should normalize existing sites');

        // Reload site and verify normalization
        $em->clear();
        $siteRepo = $em->getRepository(SonataPageSite::class);
        $normalizedFi = $siteRepo->findOneBy(['locale' => 'fi']);

        $this->assertNotNull($normalizedFi);
        $this->assertTrue($normalizedFi->getIsDefault(), 'FI site should be normalized to default');
        $this->assertTrue($normalizedFi->getEnabled(), 'FI site should be enabled');
        $this->assertSame('', $normalizedFi->getRelativePath(), 'FI site should have empty path');
    }

    /**
     * @return array{CommandTester,int}
     */
    private function runCmsSeed(array $input = []): array
    {
        return $this->runWithoutTransaction(function () use ($input) {
            $tester = $this->makeCommandTester();
            $exitCode = $tester->execute($input);

            return [$tester, $exitCode];
        });
    }

    private function runWithoutTransaction(callable $callback): mixed
    {
        $hadTransaction = PHPUnitExtension::$transactionStarted;

        if ($hadTransaction) {
            StaticDriver::rollBack();
            PHPUnitExtension::$transactionStarted = false;
        }

        StaticDriver::setKeepStaticConnections(false);

        try {
            return $callback();
        } finally {
            StaticDriver::setKeepStaticConnections(true);
            if ($hadTransaction) {
                StaticDriver::beginTransaction();
                PHPUnitExtension::$transactionStarted = true;
            }
        }
    }
}
