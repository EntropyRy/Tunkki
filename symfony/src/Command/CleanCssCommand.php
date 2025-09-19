<?php

namespace App\Command;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-css',
    description: 'Cleans fully commented out CSS in Event entity and adds dark theme variants',
)]
class CleanCssCommand extends Command
{
    private array $selectors = ['.container', '.e-container', 'body', 'a', 'input', 'button'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'add-dark-theme',
                null,
                InputOption::VALUE_NONE,
                'Add dark theme variants to specified CSS selectors'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Processing CSS in Event entities');

        $addDarkTheme = $input->getOption('add-dark-theme');
        if ($addDarkTheme) {
            $io->info('Will add dark theme variants for specified selectors');
        }

        $eventRepository = $this->entityManager->getRepository(Event::class);
        $events = $eventRepository->findAll();

        $cleanedCount = 0;
        $modifiedCount = 0;
        $skipped = 0;
        $processedEvents = [];

        foreach ($events as $event) {
            $cssContent = $event->getCss();
            $modified = false;

            // Skip if CSS is null or empty
            if ($cssContent === null || trim($cssContent) === '') {
                $skipped++;
                continue;
            }

            // Check if CSS is fully commented out
            $trimmedContent = trim($cssContent);
            if ($this->isFullyCommentedOut($trimmedContent)) {
                $io->text(sprintf('Event ID %d has fully commented CSS - cleaning...', $event->getId()));
                $cssContent = ''; // Remove the content
                $cleanedCount++;
                $modified = true;
            }

            // Add dark theme variants if requested
            if ($addDarkTheme && ($cssContent !== '' && $cssContent !== '0')) {
                $newCssContent = $this->addDarkThemeVariants($cssContent);
                if ($newCssContent !== $cssContent) {
                    $event->setCss($newCssContent);
                    $modifiedCount++;
                    $modified = true;
                }
            } elseif ($modified) {
                $event->setCss($cssContent);
            }

            // If any changes were made, add to our list of processed events
            if ($modified) {
                $processedEvents[] = [
                    'id' => $event->getId(),
                    'name' => $event->getName()
                ];
            }
        }

        // Flush changes to database
        $this->entityManager->flush();

        // Display processed events
        if ($processedEvents !== []) {
            $io->section('Processed Events:');
            $table = [];
            foreach ($processedEvents as $event) {
                $table[] = [$event['id'], $event['name']];
            }
            $io->table(['ID', 'Name'], $table);
        }

        $io->success(sprintf(
            'Processed %d events: %d were cleaned, %d had dark theme variants added, %d were skipped.',
            count($events),
            $cleanedCount,
            $modifiedCount,
            $skipped
        ));

        return Command::SUCCESS;
    }

    /**
     * Checks if the CSS content is fully commented out
     */
    private function isFullyCommentedOut(string $cssContent): bool
    {
        $trimmedContent = trim($cssContent);
        // Check if the content starts with /* and ends with */
        // No nested comment marker check as requested
        return str_starts_with($trimmedContent, '/*') && str_ends_with($trimmedContent, '*/');
    }

    /**
     * Adds dark theme variants for specified selectors
     */
    private function addDarkThemeVariants(string $cssContent): string
    {
        // Parse the CSS content to find and duplicate the specified selectors with [data-bs-theme=dark]
        $pattern = '/(' . implode('|', array_map('preg_quote', $this->selectors)) . ')\s*(\{[^}]*\})/';

        preg_match_all($pattern, $cssContent, $matches, PREG_SET_ORDER);

        $additions = [];
        foreach ($matches as $match) {
            $selector = $match[1];
            $rules = $match[2];

            // Create the dark theme variant
            $darkSelector = "[data-bs-theme=dark] $selector";
            $additions[] = "$darkSelector $rules";
        }

        // If we have any additions, append them to the CSS content
        if ($additions !== []) {
            $cssContent .= "\n\n/* Dark theme variants added automatically */\n";
            $cssContent .= implode("\n", $additions);
        }

        return $cssContent;
    }
}
