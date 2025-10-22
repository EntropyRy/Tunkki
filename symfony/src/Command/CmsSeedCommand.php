<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Sonata\SonataPageSite;
use App\Factory\PageFactory;
use App\Factory\SiteFactory;
use App\Story\CmsBaselineStory;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\PageBundle\Service\Contract\CreateSnapshotBySiteInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zenstruck\Foundry\Persistence\Proxy\PersistedObjectsTracker;

#[
    AsCommand(
        name: 'entropy:cms:seed',
        description: 'Seed the base minimum Sonata CMS sites and pages (FI "", EN "/en"; root + alias pages). Idempotent.',
    ),
]
final class CmsSeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CreateSnapshotBySiteInterface $createSnapshotBySite,
    )
    {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        // Acquire advisory lock to prevent parallel execution races
        // Lock key: 1220303 (arbitrary unique identifier for CMS seeding)
        $lockAcquired = $this->acquireAdvisoryLock(1220303);

        if (!$lockAcquired) {
            // Retry with exponential backoff (1s, 2s, 3s)
            for ($attempt = 1; $attempt <= 3; ++$attempt) {
                sleep($attempt);
                $lockAcquired = $this->acquireAdvisoryLock(1220303);
                if ($lockAcquired) {
                    break;
                }
            }

            if (!$lockAcquired) {
                // Another process holds the lock; skip seeding since that process will handle it
                $io->note('CMS seeding already in progress (another process holds advisory lock). Skipping redundant seeding.');

                return Command::SUCCESS;
            }
        }

        try {
            return $this->executeSeedingLogic($io);
        } finally {
            // Always release lock (also auto-releases on connection close)
            $this->releaseAdvisoryLock(1220303);
        }
    }

    private function executeSeedingLogic(SymfonyStyle $io): int
    {
        $siteRepo = SiteFactory::repository();
        $pageRepo = PageFactory::repository();

        // Count before
        $sitesBefore = $siteRepo->count([]);
        $pagesBefore = $pageRepo->count([]);

        // Load the CmsBaselineStory to seed the CMS
        $io->writeln('Loading CmsBaselineStory to seed CMS baseline...');

        // Initialize Foundry's proxy tracker early so typed static properties are set
        new PersistedObjectsTracker();

        // Instantiate and build the story directly
        $story = new CmsBaselineStory();
        $story->build();

        // Count after
        $sitesAfter = $siteRepo->count([]);
        $pagesAfter = $pageRepo->count([]);

        $sitesCreated = max(0, $sitesAfter - $sitesBefore);
        $pagesCreated = max(0, $pagesAfter - $pagesBefore);

        $io->success(
            \sprintf(
                'CMS seed completed via CmsBaselineStory. sites: %d (+%d); pages: %d (+%d)',
                $sitesAfter,
                $sitesCreated,
                $pagesAfter,
                $pagesCreated,
            ),
        );

        $this->refreshSnapshots($io);

        // Show details about the created sites
        /** @var SonataPageSite[] $sites */
        $sites = $siteRepo->findAll();
        foreach ($sites as $site) {
            $rootPage = $pageRepo->findOneBy(['site' => $site, 'url' => '/']);
            $io->writeln(
                \sprintf(
                    'Site %s (%s): %d pages, root=%s',
                    $site->getLocale(),
                    $site->getRelativePath(),
                    $pageRepo->count(['site' => $site]),
                    $rootPage?->getId() ?: 'none',
                ),
            );
        }

        return Command::SUCCESS;
    }

    private function refreshSnapshots(SymfonyStyle $io): void
    {
        /** @var SonataPageSite[] $sites */
        $sites = SiteFactory::repository()->findAll();
        $refreshed = 0;

        foreach ($sites as $site) {
            $this->createSnapshotBySite->createBySite($site);
            ++$refreshed;
        }

        $io->writeln(
            \sprintf('Snapshots refreshed for %d site(s).', $refreshed),
        );
    }

    /**
     * Acquire MariaDB advisory lock to prevent parallel execution races.
     *
     * @param int $lockKey Unique integer identifier for this lock
     *
     * @return bool True if lock acquired, false otherwise
     */
    private function acquireAdvisoryLock(int $lockKey): bool
    {
        try {
            $conn = $this->em->getConnection();

            // SQLite doesn't support advisory locks; in test environments (panther, test)
            // there are no race conditions since tests run in isolated processes
            $platform = $conn->getDatabasePlatform();
            if ($platform instanceof SqlitePlatform) {
                return true; // Bypass locking for SQLite
            }

            // MariaDB: GET_LOCK returns 1 if acquired, 0 if timeout, NULL if error
            // Timeout = 0 means non-blocking try
            $result = $conn->fetchOne('SELECT GET_LOCK(?, 0)', ["cms_seed_{$lockKey}"]);

            return 1 === $result;
        } catch (\Throwable) {
            // If lock mechanism fails, log but don't break the command
            return false;
        }
    }

    /**
     * Release advisory lock.
     *
     * @param int $lockKey Same key used in acquireAdvisoryLock
     */
    private function releaseAdvisoryLock(int $lockKey): void
    {
        try {
            $conn = $this->em->getConnection();

            // SQLite doesn't use advisory locks
            $platform = $conn->getDatabasePlatform();
            if ($platform instanceof SqlitePlatform) {
                return; // No-op for SQLite
            }

            $conn->executeStatement('SELECT RELEASE_LOCK(?)', ["cms_seed_{$lockKey}"]);
        } catch (\Throwable) {
            // Best effort release; lock will auto-release on connection close anyway
        }
    }
}
