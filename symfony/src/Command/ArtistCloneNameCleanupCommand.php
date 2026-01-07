<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Artist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('entropy:artist-clone:cleanup-names')]
final class ArtistCloneNameCleanupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Remove legacy "for <event>" suffix from archived artist clone names.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without saving.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $artists = $this->em
            ->getRepository(Artist::class)
            ->createQueryBuilder('a')
            ->andWhere('a.copyForArchive = :archive')
            ->andWhere('a.name LIKE :needle')
            ->setParameter('archive', true)
            ->setParameter('needle', '% for %')
            ->getQuery()
            ->getResult();

        $updated = 0;
        foreach ($artists as $artist) {
            if (!$artist instanceof Artist) {
                continue;
            }
            $current = $artist->getName();
            $cleaned = $this->stripLegacySuffix($current);

            if ($cleaned === $current) {
                continue;
            }

            ++$updated;
            $io->writeln(sprintf('"%s" => "%s"', $current, $cleaned));

            if (!$dryRun) {
                $artist->setName($cleaned);
                $this->em->persist($artist);
            }
        }

        if (!$dryRun && $updated > 0) {
            $this->em->flush();
        }

        if ($dryRun) {
            $io->success(sprintf('Dry run complete. %d artist clone names would be updated.', $updated));
        } else {
            $io->success(sprintf('Updated %d artist clone names.', $updated));
        }

        return Command::SUCCESS;
    }

    private function stripLegacySuffix(string $name): string
    {
        $cleaned = preg_replace('/\s+for\s+.+$/', '', $name);
        if (null === $cleaned) {
            return $name;
        }

        return trim($cleaned);
    }
}
