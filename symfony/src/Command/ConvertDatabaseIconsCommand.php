<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:convert-database-icons',
    description: 'Convert FontAwesome icons in database to simple icon aliases',
)]
class ConvertDatabaseIconsCommand extends Command
{
    /**
     * FontAwesome icon name mappings to FA6 and simple-icons equivalents
     */
    private const ICON_MAPPINGS = [
        // FontAwesome 4/5 to FA6 name changes
        'medkit' => 'kit-medical',
        'pencil-square-o' => 'pen-to-square',
        'history' => 'clock-rotate-left',
        'home' => 'house',
        'arrow-circle-right' => 'circle-arrow-right',
        'sign-in-alt' => 'right-to-bracket',
        'sign-out-alt' => 'right-from-bracket',
        'check-square' => 'square-check',
        'users-cog' => 'users-gear',
        'eye-slash' => 'eye-slash',
        'list-alt' => 'rectangle-list',
        'pencil' => 'pen',
        'trash-o' => 'trash',
        'tasks' => 'list-check',   // There's no tasks icon in FA6, list-check is closest
    ];
    
    /**
     * FontAwesome brand icons to simple-icons mappings
     */
    private const BRAND_ICON_MAPPINGS = [
        'facebook' => 'facebook',
        'facebook-f' => 'facebook',
        'twitter' => 'twitter',
        'youtube' => 'youtube',
        'instagram' => 'instagram',
        'spotify' => 'spotify',
        'soundcloud' => 'soundcloud',
        'bandcamp' => 'bandcamp',
        'apple' => 'apple',
        'linkedin' => 'linkedin',
        'github' => 'github',
        'discord' => 'discord',
        'tiktok' => 'tiktok',
        'twitch' => 'twitch',
        'telegram' => 'telegram',
        'whatsapp' => 'whatsapp',
        'mastodon' => 'mastodon',
        'pinterest' => 'pinterest',
        'reddit' => 'reddit',
        'behance-square' => 'behance',
        'behance' => 'behance',
        'angellist' => 'angellist',
        'mixcloud' => 'mixcloud',
    ];
    
    /**
     * Special mappings for icons that need to be redirected to a different icon set
     */
    private const SPECIAL_ICON_MAPPINGS = [
        'angellist' => 'logos:angellist',
        'fish' => 'fa6-solid:fish',
        'browser' => 'fa6-solid:link',
        'broadcast-tower' => 'fa6-solid:tower-broadcast',
    ];
    
    /**
     * Mapping from full icon paths to simple aliases (reverse of ux_icons.yaml aliases)
     */
    private const ICON_TO_ALIAS_MAPPINGS = [
        // Brand icons
        'simple-icons:facebook' => 'facebook',
        'simple-icons:twitter' => 'twitter',
        'simple-icons:youtube' => 'youtube',
        'simple-icons:instagram' => 'instagram',
        'simple-icons:spotify' => 'spotify',
        'simple-icons:soundcloud' => 'soundcloud',
        'simple-icons:bandcamp' => 'bandcamp',
        'simple-icons:apple' => 'apple',
        'simple-icons:linkedin' => 'linkedin',
        'simple-icons:github' => 'github',
        'simple-icons:discord' => 'discord',
        'simple-icons:tiktok' => 'tiktok',
        'simple-icons:twitch' => 'twitch',
        'simple-icons:telegram' => 'telegram',
        'simple-icons:whatsapp' => 'whatsapp',
        'simple-icons:mastodon' => 'mastodon',
        'simple-icons:pinterest' => 'pinterest',
        'simple-icons:reddit' => 'reddit',
        'simple-icons:behance' => 'behance',
        'logos:angellist' => 'angellist',
        'simple-icons:mixcloud' => 'mixcloud',
        
        // Common UI icons
        'fa6-solid:user' => 'user',
        'fa6-solid:users' => 'users',
        'fa6-solid:house' => 'home',
        'fa6-solid:music' => 'music',
        'fa6-solid:link' => 'link',
        'fa6-solid:envelope' => 'email',
        'fa6-solid:phone' => 'phone',
        'fa6-solid:calendar' => 'calendar',
        'fa6-solid:clock' => 'clock',
        'fa6-solid:download' => 'download',
        'fa6-solid:upload' => 'upload',
        'fa6-solid:magnifying-glass' => 'search',
        'fa6-solid:pen' => 'edit',
        'fa6-solid:trash' => 'trash',
        'fa6-solid:xmark' => 'remove',
        'fa6-solid:check' => 'check',
        'fa6-solid:plus' => 'plus',
        'fa6-solid:minus' => 'minus',
        'fa6-solid:arrow-right' => 'arrow-right',
        'fa6-solid:arrow-left' => 'arrow-left',
        'fa6-solid:arrow-up' => 'arrow-up',
        'fa6-solid:arrow-down' => 'arrow-down',
        'fa6-solid:gear' => 'settings',
        'fa6-solid:info' => 'info',
        'fa6-solid:triangle-exclamation' => 'warning',
        'fa6-solid:circle-exclamation' => 'error',
        'fa6-solid:circle-check' => 'success',
        'fa6-solid:star' => 'star',
        'fa6-solid:heart' => 'heart',
        'fa6-solid:bookmark' => 'bookmark',
        'fa6-solid:share' => 'share',
        'fa6-solid:print' => 'print',
        'fa6-solid:floppy-disk' => 'save',
        'fa6-solid:copy' => 'copy',
        'fa6-solid:scissors' => 'cut',
        'fa6-solid:paste' => 'paste',
        'fa6-solid:folder' => 'folder',
        'fa6-solid:file' => 'file',
        'fa6-solid:image' => 'image',
        'fa6-solid:video' => 'video',
        'fa6-solid:file-text' => 'document',
        'fa6-solid:file-pdf' => 'pdf',
        'fa6-solid:bars' => 'menu',
        'fa6-solid:table-cells' => 'grid',
        'fa6-solid:list' => 'list',
        'fa6-solid:address-card' => 'card',
        'fa6-solid:table' => 'table',
        'fa6-solid:bullhorn' => 'bullhorn',
        'fa6-solid:tower-broadcast' => 'broadcast',
        'fa6-solid:play' => 'play',
        'fa6-solid:pause' => 'pause',
        'fa6-solid:stop' => 'stop',
        'fa6-solid:record-vinyl' => 'record',
        'fa6-solid:volume-high' => 'volume',
        'fa6-solid:volume-xmark' => 'mute',
        'fa6-solid:lock' => 'lock',
        'fa6-solid:unlock' => 'unlock',
        'fa6-solid:eye' => 'visible',
        'fa6-solid:eye-slash' => 'hidden',
        'fa6-solid:cart-shopping' => 'cart',
        'fa6-solid:dollar-sign' => 'money',
        'fa6-solid:credit-card' => 'payment',
        'fa6-solid:building-columns' => 'bank',
        'fa6-solid:chart-line' => 'chart',
        'fa6-solid:chart-bar' => 'analytics',
        'fa6-solid:file-chart-line' => 'report',
        'fa6-solid:message' => 'message',
        'fa6-solid:comment' => 'chat',
        'fa6-solid:bell' => 'notification',
        'fa6-solid:kit-medical' => 'medkit',
        'fa6-solid:clock-rotate-left' => 'history',
        'fa6-solid:list-check' => 'tasks',
        'fa6-solid:scale-balanced' => 'balance-scale',
        'fa6-solid:book' => 'book',
        'fa6-solid:fish' => 'fish',
        'fa6-solid:pen-to-square' => 'pencil-square-o',
        'fa6-solid:circle-arrow-right' => 'arrow-circle-right',
        'fa6-solid:right-to-bracket' => 'sign-in-alt',
        'fa6-solid:right-from-bracket' => 'sign-out-alt',
        'fa6-solid:square-check' => 'check-square',
        'fa6-solid:users-gear' => 'users-cog',
        'fa6-solid:rectangle-list' => 'list-alt',
        'fa6-solid:cube' => 'cube',
        'fa6-solid:smoking' => 'smoking',
        'fa6-solid:compact-disc' => 'cd',
        'fa6-solid:location-dot' => 'location-dot',
        'fa6-solid:map-pin' => 'location-dot',
        'fa6-solid:coffee' => 'coffee',
        'fa6-solid:ticket-alt' => 'ticket',
        'fa6-solid:instagram' => 'instagram',
        'fa6-solid:up-right-from-square' => 'link-out',
        'fa6-solid:right-to-bracket' => 'sign-in',
        'fa6-solid:square-rss' => 'rss',
        'fa6-regular:heart' => 'heart',
        'fa6-solid:map-marker-alt' => 'map-pin',
        'fa6-solid:map' => 'map-pin',
        'fa6-solid:comments' => 'comments',
        'fa6-solid:bus' => 'bus',
        'fa6-regular:smile' => 'smiley',
        'fa6-solid:cubes' => 'cubes',
        
        // E30V specific icons
        'fa6-solid:link' => 'link',
        'simple-icons:instagram' => 'instagram',
        'simple-icons:telegram' => 'telegram',
        'brands:ra' => 'ra',
        'simple-icons:facebook' => 'facebook',
        'fa6-solid:hand-holding-heart' => 'hand-holding-heart',
        'fa:wikipedia-w' => 'wiki',
        'fa6-solid:shirt' => 't-shirt',
        'simple-icons:linktree' => 'linktree',
    ];
    
    // Cache for verified icons
    private array $verifiedIcons = [];

    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Verify icon aliases exist by checking their full icon paths')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show alias conversions without applying them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Converting FontAwesome icons in database to simple icon aliases');
        
        $verifyEnabled = $input->getOption('verify');
        $dryRun = $input->getOption('dry-run');
        
        if ($dryRun) {
            $io->info('DRY RUN MODE: No changes will be applied to the database');
        }
        
        if ($verifyEnabled) {
            $io->info('Icon verification enabled - will check alias mappings before conversion');
        }
        
        try {
            // Process different entity types
            $this->processLinkListBlocks($io, $verifyEnabled, $dryRun);
            $this->processArtists($io, $verifyEnabled, $dryRun);
            $this->processEvents($io, $verifyEnabled, $dryRun);
            
            $io->success('Icon to alias conversion process completed.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error converting icons to aliases: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
    
    /**
     * Process LinkListBlocks with icons in their settings
     */
    private function processLinkListBlocks(SymfonyStyle $io, bool $verifyEnabled, bool $dryRun): void
    {
        $io->section('Processing LinkListBlock entities');
        
        $blockQueryResult = $this->connection->executeQuery("
            SELECT id, settings, type
            FROM page__block 
            WHERE type = 'entropy_tunkki.block.linklist' OR settings LIKE '%\"icon\"%'
        ");
        
        $blocks = $blockQueryResult->fetchAllAssociative();
        
        if (empty($blocks)) {
            $io->info('No blocks found with icon data.');
            return;
        }
        
        $io->info(sprintf('Found %d blocks to process.', count($blocks)));
        
        $updatedCount = 0;
        $blocksUpdated = 0;
        
        foreach ($blocks as $block) {
            $blockType = $block['type'] ?? 'Unknown';
            $io->text(sprintf('Processing block #%d (Type: %s)', $block['id'], $blockType));
            
            $settings = json_decode($block['settings'], true);
            $updated = false;
            
            if (isset($settings['urls']) && is_array($settings['urls'])) {
                foreach ($settings['urls'] as $key => $url) {
                    if (isset($url['icon']) && !empty($url['icon'])) {
                        $oldIcon = $url['icon'];
                        // Skip if already converted (either contains colon or is a known alias)
                        if ($this->isIconAlreadyConverted($oldIcon)) {
                            continue;
                        }
                            
                        $newIcon = $this->convertFontAwesomeToSymfonyUx($oldIcon);
                            
                        // Verify icon if enabled
                        if ($verifyEnabled && !$this->verifyIcon($io, $newIcon)) {
                            $io->text(sprintf('  - <fg=yellow>Skipping icon</> %s (not found)', $newIcon));
                            continue;
                        }
                            
                        if ($oldIcon !== $newIcon) {
                            if (!$dryRun) {
                                $settings['urls'][$key]['icon'] = $newIcon;
                            }
                            $updated = true;
                            $updatedCount++;
                                
                            $io->text(sprintf('  - Converted to alias: <info>%s</info> → <info>%s</info>', 
                                $oldIcon, $newIcon));
                        }
                    }
                }
            }
            
            if ($updated && !$dryRun) {
                $this->connection->executeStatement(
                    "UPDATE page__block SET settings = :settings WHERE id = :id",
                    [
                        'settings' => json_encode($settings),
                        'id' => $block['id']
                    ]
                );
                $blocksUpdated++;
                $io->text(sprintf('✓ Updated block #%d', $block['id']));
            }
        }
        
        if ($updatedCount > 0) {
            $io->success(sprintf('Converted %d icons to aliases in %d blocks', $updatedCount, $blocksUpdated));
            if ($dryRun) {
                $io->info('No changes were applied (dry run mode)');
            }
        } else {
            $io->info('No block icons needed to be converted to aliases');
        }
    }
    
    /**
     * Process Artist entities with icons
     */
    private function processArtists(SymfonyStyle $io, bool $verifyEnabled, bool $dryRun): void
    {
        $io->section('Processing Artist entities');
        
        try {
            // Check if the table exists first
            $tableExists = $this->connection->executeQuery("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'artist'
            ")->fetchOne();
            
            if (!$tableExists) {
                $io->info('Artist table not found. Skipping.');
                return;
            }
            
            // Check for artists with links containing icons
            $artistQuery = $this->connection->executeQuery("
                SELECT id, name, links
                FROM artist
                WHERE links LIKE '%icon%'
            ");
            
            $artists = $artistQuery->fetchAllAssociative();
            
            if (empty($artists)) {
                $io->info('No artists found with icon data.');
                return;
            }
            
            $io->info(sprintf('Found %d artists to process.', count($artists)));
            
            $updatedCount = 0;
            $artistsUpdated = 0;
            
            foreach ($artists as $artist) {
                $links = json_decode($artist['links'], true);
                $updated = false;
                
                if (is_array($links)) {
                    foreach ($links as $key => $link) {
                        if (isset($link['icon']) && !empty($link['icon'])) {
                            $oldIcon = $link['icon'];
                            // Skip if already converted (either contains colon or is a known alias)
                            if ($this->isIconAlreadyConverted($oldIcon)) {
                                continue;
                            }
                            
                            $newIcon = $this->convertFontAwesomeToSymfonyUx($oldIcon);
                            
                            // Verify icon if enabled
                            if ($verifyEnabled && !$this->verifyIcon($io, $newIcon)) {
                                $io->text(sprintf('  - <fg=yellow>Skipping icon</> %s (not found)', $newIcon));
                                continue;
                            }
                            
                            if ($oldIcon !== $newIcon) {
                                if (!$dryRun) {
                                    $links[$key]['icon'] = $newIcon;
                                }
                                $updated = true;
                                $updatedCount++;
                                
                                $io->text(sprintf('  - Artist #%d (%s): Converted to alias <info>%s</info> → <info>%s</info>',
                                    $artist['id'], $artist['name'], $oldIcon, $newIcon));
                            }
                        }
                    }
                }
                
                if ($updated && !$dryRun) {
                    $this->connection->executeStatement(
                        "UPDATE artist SET links = :links WHERE id = :id",
                        [
                            'links' => json_encode($links),
                            'id' => $artist['id']
                        ]
                    );
                    $artistsUpdated++;
                    $io->text(sprintf('✓ Updated artist #%d', $artist['id']));
                }
            }
            
            if ($updatedCount > 0) {
                $io->success(sprintf('Converted %d icons to aliases in %d artists', $updatedCount, $artistsUpdated));
                if ($dryRun) {
                    $io->info('No changes were applied (dry run mode)');
                }
            } else {
                $io->info('No artist icons needed to be converted to aliases');
            }
        } catch (\Exception $e) {
            $io->warning(sprintf('Error processing artists: %s', $e->getMessage()));
        }
    }
    
    /**
     * Process Event entities with icons
     */
    private function processEvents(SymfonyStyle $io, bool $verifyEnabled, bool $dryRun): void
    {
        $io->section('Processing Event entities');
        
        try {
            // Check if the table exists first
            $tableExists = $this->connection->executeQuery("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'event'
            ")->fetchOne();
            
            if (!$tableExists) {
                $io->info('Event table not found. Skipping.');
                return;
            }
            
            // Check for events with links containing icons
            $eventQuery = $this->connection->executeQuery("
                SELECT id, name, links
                FROM event
                WHERE links LIKE '%icon%'
            ");
            
            $events = $eventQuery->fetchAllAssociative();
            
            if (empty($events)) {
                $io->info('No events found with icon data.');
                return;
            }
            
            $io->info(sprintf('Found %d events to process.', count($events)));
            
            $updatedCount = 0;
            $eventsUpdated = 0;
            
            foreach ($events as $event) {
                $links = json_decode($event['links'], true);
                $updated = false;
                
                if (is_array($links)) {
                    // Check if links directly contains a urls array (common structure)
                    if (isset($links['urls']) && is_array($links['urls'])) {
                        foreach ($links['urls'] as $urlKey => $url) {
                            if (isset($url['icon']) && !empty($url['icon'])) {
                                $oldIcon = $url['icon'];
                                // Skip if already converted (either contains colon or is a known alias)
                                if ($this->isIconAlreadyConverted($oldIcon)) {
                                    continue;
                                }
                        
                                $newIcon = $this->convertFontAwesomeToSymfonyUx($oldIcon);
                        
                                // Verify icon if enabled
                                if ($verifyEnabled && !$this->verifyIcon($io, $newIcon)) {
                                    $io->text(sprintf('  - <fg=yellow>Skipping icon</> %s (not found)', $newIcon));
                                    continue;
                                }
                        
                                if ($oldIcon !== $newIcon) {
                                    if (!$dryRun) {
                                        $links['urls'][$urlKey]['icon'] = $newIcon;
                                    }
                                    $updated = true;
                                    $updatedCount++;
                                    
                                    $io->text(sprintf('  - Event #%d (%s): Converted to alias <info>%s</info> → <info>%s</info>',
                                        $event['id'], $event['name'], $oldIcon, $newIcon));
                                }
                            }
                        }
                    } else {
                        // Original logic for nested structure
                        foreach ($links as $key => $link) {
                            // Check if there are nested URLs in the links array
                            if (isset($link['urls']) && is_array($link['urls'])) {
                                // Process nested URLs array
                                foreach ($link['urls'] as $urlKey => $url) {
                                    if (isset($url['icon']) && !empty($url['icon'])) {
                                        $oldIcon = $url['icon'];
                                        // Skip if already converted (either contains colon or is a known alias)
                                        if ($this->isIconAlreadyConverted($oldIcon)) {
                                            continue;
                                        }
                                
                                        $newIcon = $this->convertFontAwesomeToSymfonyUx($oldIcon);
                                
                                        // Verify icon if enabled
                                        if ($verifyEnabled && !$this->verifyIcon($io, $newIcon)) {
                                            $io->text(sprintf('  - <fg=yellow>Skipping icon</> %s (not found)', $newIcon));
                                            continue;
                                        }
                                
                                        if ($oldIcon !== $newIcon) {
                                            if (!$dryRun) {
                                                $links[$key]['urls'][$urlKey]['icon'] = $newIcon;
                                            }
                                            $updated = true;
                                            $updatedCount++;
                                            
                                            $io->text(sprintf('  - Event #%d (%s): Converted to alias <info>%s</info> → <info>%s</info>',
                                                $event['id'], $event['name'], $oldIcon, $newIcon));
                                        }
                                    }
                                }
                            }
                            // Also check the direct icon in this level
                            elseif (isset($link['icon']) && !empty($link['icon'])) {
                                $oldIcon = $link['icon'];
                                // Skip if already converted (either contains colon or is a known alias)
                                if ($this->isIconAlreadyConverted($oldIcon)) {
                                    continue;
                                }
                                
                                $newIcon = $this->convertFontAwesomeToSymfonyUx($oldIcon);
                                
                                // Verify icon if enabled
                                if ($verifyEnabled && !$this->verifyIcon($io, $newIcon)) {
                                    $io->text(sprintf('  - <fg=yellow>Skipping icon</> %s (not found)', $newIcon));
                                    continue;
                                }
                                
                                if ($oldIcon !== $newIcon) {
                                    if (!$dryRun) {
                                        $links[$key]['icon'] = $newIcon;
                                    }
                                    $updated = true;
                                    $updatedCount++;
                                    
                                    $io->text(sprintf('  - Event #%d (%s): Converted to alias <info>%s</info> → <info>%s</info>',
                                        $event['id'], $event['name'], $oldIcon, $newIcon));
                                }
                            }
                        }
                    }
                }
                
                if ($updated && !$dryRun) {
                    $this->connection->executeStatement(
                        "UPDATE event SET links = :links WHERE id = :id",
                        [
                            'links' => json_encode($links),
                            'id' => $event['id']
                        ]
                    );
                    $eventsUpdated++;
                    $io->text(sprintf('✓ Updated event #%d', $event['id']));
                }
            }
            
            if ($updatedCount > 0) {
                $io->success(sprintf('Converted %d icons to aliases in %d events', $updatedCount, $eventsUpdated));
                if ($dryRun) {
                    $io->info('No changes were applied (dry run mode)');
                }
            } else {
                $io->info('No event icons needed to be converted to aliases');
            }
        } catch (\Exception $e) {
            $io->warning(sprintf('Error processing events: %s', $e->getMessage()));
        }
    }
    
    /**
     * Verify if an icon exists by trying to find it with the ux:icons:search command
     */
    private function verifyIcon(SymfonyStyle $io, string $icon): bool
    {
        // For aliases (no colon), convert to full icon path for verification
        $iconToCheck = $icon;
        if (!str_contains($icon, ':')) {
            // This is an alias, convert it to full path using our reverse mapping
            $fullIconPath = $this->convertAliasToFullPath($icon);
            if ($fullIconPath) {
                $iconToCheck = $fullIconPath;
            } else {
                // If we can't find a mapping, assume the alias doesn't exist
                $this->verifiedIcons[$icon] = false;
                return false;
            }
        }
        
        // Return from cache if already checked
        if (array_key_exists($iconToCheck, $this->verifiedIcons)) {
            return $this->verifiedIcons[$iconToCheck];
        }
        
        // Split icon name to get prefix and name
        if (!str_contains($iconToCheck, ':')) {
            $this->verifiedIcons[$iconToCheck] = false;
            return false;
        }
        
        list($iconSet, $iconName) = explode(':', $iconToCheck);
        
        // Get application from the current command
        $application = $this->getApplication();
        if (!$application) {
            return true; // Default to true if we can't check
        }
        
        try {
            // Find the ux:icons:search command
            $searchCommand = $application->find('ux:icons:search');
            
            // Set up arguments
            $arguments = [
                'command' => 'ux:icons:search',
                'prefix' => $iconSet,
                'name' => $iconName
            ];
            
            // Execute the command
            $input = new ArrayInput($arguments);
            $output = new BufferedOutput();
            
            $searchCommand->run($input, $output);
            $result = $output->fetch();
            
            // Parse output to check if icon exists
            $exists = strpos($result, 'Found 0 icons') === false && 
                    strpos($result, $iconName) !== false;
            
            // Cache the result for both the original icon and the checked icon
            $this->verifiedIcons[$iconToCheck] = $exists;
            $this->verifiedIcons[$icon] = $exists;
            
            if ($exists) {
                $io->text(sprintf('  - <info>Verified:</info> %s ✅', $icon));
            }
            
            return $exists;
        } catch (\Exception $e) {
            // If there's an error, assume the icon exists (failsafe)
            return true;
        }
    }
    

    
    /**
     * Convert FontAwesome classes to simple icon aliases
     */
    private function convertFontAwesomeToSymfonyUx(string $fontAwesomeClass): string
    {
        $fullIconPath = $this->convertToFullIconPath($fontAwesomeClass);
        
        // Check if we have an alias for this full icon path
        if (isset(self::ICON_TO_ALIAS_MAPPINGS[$fullIconPath])) {
            return self::ICON_TO_ALIAS_MAPPINGS[$fullIconPath];
        }
        
        // If no alias found, return the full path
        return $fullIconPath;
    }
    
    /**
     * Convert alias to full icon path for verification
     */
    private function convertAliasToFullPath(string $alias): ?string
    {
        // Search through our alias mappings to find the full path
        foreach (self::ICON_TO_ALIAS_MAPPINGS as $fullPath => $aliasName) {
            if ($aliasName === $alias) {
                return $fullPath;
            }
        }
        return null;
    }
    
    /**
     * Check if an icon is already converted (either full path with colon or known alias)
     */
    private function isIconAlreadyConverted(string $icon): bool
    {
        // If it contains a colon, it's a full icon path (already converted)
        if (strpos($icon, ':') !== false) {
            return true;
        }
        
        // If it's one of our known aliases, it's already converted
        if (in_array($icon, self::ICON_TO_ALIAS_MAPPINGS)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert FontAwesome classes to full Symfony UX Icons format (internal helper)
     */
    private function convertToFullIconPath(string $fontAwesomeClass): string
    {
        // Handle format: fa fa-something, fas fa-something
        if (preg_match('/\b(fa[bsrl]?|fas|far|fab)\s+fa-([a-z0-9-]+)/', $fontAwesomeClass, $matches)) {
            $prefix = $matches[1];
            $iconName = $matches[2];
            
            // Apply icon name mapping if exists
            if (isset(self::ICON_MAPPINGS[$iconName])) {
                $iconName = self::ICON_MAPPINGS[$iconName];
            }
            
            // Map the FontAwesome prefix to the corresponding Symfony UX icon set
            if ($prefix === 'far') {
                return 'fa6-regular:' . $iconName;
            } elseif ($prefix === 'fab') {
                // For brand icons, check if there's a special mapping first
                if (isset(self::SPECIAL_ICON_MAPPINGS[$iconName])) {
                    return self::SPECIAL_ICON_MAPPINGS[$iconName];
                }
                // Then check for brand-specific mappings
                if (isset(self::BRAND_ICON_MAPPINGS[$iconName])) {
                    return 'simple-icons:' . self::BRAND_ICON_MAPPINGS[$iconName];
                }
                return 'simple-icons:' . $iconName;
            } else {
                // Check for special non-brand mappings
                if (isset(self::SPECIAL_ICON_MAPPINGS[$iconName])) {
                    return self::SPECIAL_ICON_MAPPINGS[$iconName];
                }
                // Default to solid for all other prefixes (fa, fas, etc)
                return 'fa6-solid:' . $iconName;
            }
        }
        
        // Handle format: fa-solid fa-something
        if (preg_match('/\bfa-(solid|regular|brands|light)\s+fa-([a-z0-9-]+)/', $fontAwesomeClass, $matches)) {
            $type = $matches[1];
            $iconName = $matches[2];
            
            // Apply icon name mapping if exists
            if (isset(self::ICON_MAPPINGS[$iconName])) {
                $iconName = self::ICON_MAPPINGS[$iconName];
            }
            
            // Use simple-icons for brands, fa6 for others
            if ($type === 'brands') {
                // Check for special mappings first
                if (isset(self::SPECIAL_ICON_MAPPINGS[$iconName])) {
                    return self::SPECIAL_ICON_MAPPINGS[$iconName];
                }
                // Then check for brand-specific mappings
                if (isset(self::BRAND_ICON_MAPPINGS[$iconName])) {
                    return 'simple-icons:' . self::BRAND_ICON_MAPPINGS[$iconName];
                }
                return 'simple-icons:' . $iconName;
            }
            // Check for special non-brand mappings
            if (isset(self::SPECIAL_ICON_MAPPINGS[$iconName])) {
                return self::SPECIAL_ICON_MAPPINGS[$iconName];
            }
            return 'fa6-' . $type . ':' . $iconName;
        }
        
        // Handle just the icon name without prefix (fa-xxx)
        if (preg_match('/\bfa-([a-z0-9-]+)/', $fontAwesomeClass, $matches)) {
            $iconName = $matches[1];
            
            // Apply icon name mapping if exists
            if (isset(self::ICON_MAPPINGS[$iconName])) {
                $iconName = self::ICON_MAPPINGS[$iconName];
            }
            
            // Check for special mappings first
            if (isset(self::SPECIAL_ICON_MAPPINGS[$iconName])) {
                return self::SPECIAL_ICON_MAPPINGS[$iconName];
            }
            
            // Check for brand names in bare icons
            if (isset(self::BRAND_ICON_MAPPINGS[$iconName])) {
                return 'simple-icons:' . self::BRAND_ICON_MAPPINGS[$iconName];
            }
            
            // Default to solid
            return 'fa6-solid:' . $iconName;
        }
        
        // If we can't match any pattern, default to original string
        return $fontAwesomeClass;
    }
}