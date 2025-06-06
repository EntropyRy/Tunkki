<?php

namespace App\Command;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:replace-template-icons',
    description: 'Replace FontAwesome icons in templates with Symfony UX icons',
)]
class ReplaceTemplateIconsCommand extends Command
{
    // Maps FA icons to their Symfony UX equivalent
    private const array FA_TO_UX_MAPPINGS = [
        // Regular icons that need special mapping
        'fa-check-square' => 'square-check',
        'fa-users-cog' => 'users-gear',
        
        // Legacy FA icons mapped to their closest FA6 equivalents
        'fa-medkit' => 'kit-medical',
        'fa-pencil-square-o' => 'pen-to-square',
        'fa-history' => 'clock-rotate-left',
        'fa-home' => 'house',
        'fa-arrow-circle-right' => 'circle-arrow-right',
        'fa-sign-in-alt' => 'right-to-bracket',
        'fa-sign-out-alt' => 'right-from-bracket',
        
        // Any other special cases can be added here
    ];
    
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Attempt to replace the icons (BE CAREFUL: BACKUP FIRST)')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Path to search for templates', 'templates')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Verify icon existence using ux:icons:search')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('FontAwesome to Symfony UX Icons Converter');
        
        $path = $this->projectDir . '/' . $input->getOption('path');
        $writeMode = $input->getOption('write');
        $verifyMode = $input->getOption('verify');
        
        if ($writeMode) {
            $io->warning([
                'You have enabled write mode!',
                'This will modify your template files.',
                'Make sure you have backed up your templates before proceeding.',
            ]);
            
            if (!$io->confirm('Do you want to continue?', false)) {
                $io->note('Operation cancelled');
                return Command::SUCCESS;
            }
        }
        
        // Initialize icon verification system if needed
        $verifiedIcons = [];
        if ($verifyMode) {
            $io->info('Icon verification enabled - will check icons before replacement');
        }
        
        $io->section('Searching for FontAwesome icons in templates...');
        
        $finder = new Finder();
        $finder->files()
            ->in($path)
            ->notPath('admin')  // Exclude templates/admin folder
            ->name('*.twig');
            
        $stats = [
            'files_processed' => 0,
            'files_updated' => 0,
            'replacements_made' => 0,
            'replacement_skipped' => 0,
        ];

        foreach ($finder as $file) {
            $content = $file->getContents();
            $stats['files_processed']++;
            
            $fileNameDisplayed = false;
            $fileModified = false;
            
            // Process standard icon tags
            [$newContent, $fileNameDisplayed, $fileModified] = $this->processStandardIcons(
                $content, 
                $io, 
                $writeMode, 
                $file->getRelativePathname(),
                $verifyMode,
                $verifiedIcons,
                $stats, 
                $fileNameDisplayed, 
                $fileModified
            );
            
            // Save the file if modified and in write mode
            if ($writeMode && $fileModified) {
                file_put_contents($file->getRealPath(), $newContent);
                $stats['files_updated']++;
            }
        }
            
        $io->section('Summary');
        $io->definitionList(
            ['Files Processed' => $stats['files_processed']],
            ['Files Updated' => $stats['files_updated']],
            ['Icons Replaced' => $stats['replacements_made']],
            ['Replacements Skipped' => $stats['replacement_skipped']]
        );
        
        if (!$writeMode) {
            $io->info([
                'This was a dry run. No files were modified.',
                'Run the command with --write to apply changes.',
                'IMPORTANT: Always back up your templates before applying changes!'
            ]);
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Verify if an icon exists in the Symfony UX Icons package
     */
    private function verifyIcon(SymfonyStyle $io, string $iconSet, string $iconName): bool
    {
        $fullName = $iconSet . ':' . $iconName;
        
        // Get application from the current command
        $application = $this->getApplication();
        if (!$application instanceof Application) {
            $io->warning('Could not access application to verify icons');
            return false;
        }
        
        // Prepare to call the ux:icons:search command
        $searchCommand = $application->find('ux:icons:search');
        $arguments = [
            'command' => 'ux:icons:search',
            'prefix' => $iconSet,
            'name' => $iconName
        ];
        
        $input = new ArrayInput($arguments);
        $output = new BufferedOutput();
        
        try {
            $searchCommand->run($input, $output);
            $result = $output->fetch();
            
            // Parse the output to check if the icon exists
            $exists = !str_contains($result, 'Found 0 icons') && 
                     str_contains($result, $iconName);
            
            if ($exists) {
                $io->text(sprintf(' - <info>Verified</info>: %s ✅', $fullName));
            }
            
            return $exists;
        } catch (\Exception $e) {
            $io->error(sprintf('Error verifying icon %s: %s', $fullName, $e->getMessage()));
            return false;
        }
    }
    
    /**
     * Process standard FontAwesome icons
     */
    private function processStandardIcons(
        string $content, 
        SymfonyStyle $io, 
        bool $writeMode,
        string $filename,
        bool $verifyMode,
        array $verifiedIcons,
        array &$stats, 
        bool &$fileNameDisplayed,
        bool &$fileModified
    ): array {
        $newContent = $content;
        $adjustedOffset = 0;
        
        // Find all standard FontAwesome icons
        $pattern = '/<i\s+class=("|\')((?:fa[bsrl]?|fas|far|fab|fa-solid|fa-regular|fa-brands|fa-light)[^"\']*fa-([a-z0-9-]+)[^"\']*)("|\')([^>]*)><\/i>/i';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            if (!$fileNameDisplayed) {
                $io->text(sprintf('<info>File:</info> %s', $filename));
                $fileNameDisplayed = true;
            }
            
            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $matchPos = $match[0][1];
                $quote = $match[1][0];
                $iconClass = $match[2][0];
                $iconName = $match[3][0];
                //$extraAttrs = isset($match[5]) ? $match[5][0] : '';
                
                // Determine the proper icon set (solid vs regular)
                $iconSet = 'fa6-solid';
                if (str_contains($iconClass, 'far ') || 
                    str_contains($iconClass, 'fa-regular') ||
                    str_contains($iconClass, 'fa-regular:')) {
                    $iconSet = 'fa6-regular';
                }
                
                // Handle special cases for renamed icons
                if (isset(self::FA_TO_UX_MAPPINGS['fa-' . $iconName])) {
                    $iconName = self::FA_TO_UX_MAPPINGS['fa-' . $iconName];
                    // If we've mapped to a different icon name, reset the verification status
                    if ($verifyMode) {
                        $uxIconName = $iconSet . ':' . $iconName;
                        unset($verifiedIcons[$uxIconName]);
                    }
                }
                
                $uxIconName = $iconSet . ':' . $iconName;
                
                // Verify icon exists if verification is enabled
                if ($verifyMode) {
                    if (!isset($verifiedIcons[$uxIconName])) {
                        // Check if the icon exists using the console command
                        $exists = $this->verifyIcon($io, $iconSet, $iconName);
                        $verifiedIcons[$uxIconName] = $exists;
                    }
                    
                    if (!$verifiedIcons[$uxIconName]) {
                        $io->text(sprintf(' - <fg=yellow>Skipping</> icon %s (not found): <comment>%s</comment>', $iconName, $fullMatch));
                        $stats['replacement_skipped']++;
                        continue;
                    }
                }
                
                $replacement = '<twig:ux:icon name="' . $uxIconName . '" />';
                
                $io->text(sprintf(' - Replace: <comment>%s</comment>', $fullMatch));
                $io->text(sprintf('   with: <info>%s</info>', $replacement));
                
                if ($writeMode) {
                    // Adjust position to account for previous replacements
                    $actualPos = $matchPos + $adjustedOffset;
                    $newContent = substr_replace($newContent, $replacement, $actualPos, strlen($fullMatch));
                    $adjustedOffset += strlen($replacement) - strlen($fullMatch);
                    $stats['replacements_made']++;
                    $fileModified = true;
                }
            }
        }
        
        return [$newContent, $fileNameDisplayed, $fileModified];
    }
}
