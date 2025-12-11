<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:block-to-component',
    description: 'Convert a Sonata Block to a Symfony TwigComponent',
)]
class BlockToTwigComponentCommand extends Command
{
    private const string BLOCK_DIR = '/src/Block/';
    private const string COMPONENT_DIR = '/src/Twig/Components/';
    private const string BLOCK_TEMPLATE_DIR = '/templates/block/';
    private const string COMPONENT_TEMPLATE_DIR = '/templates/components/';

    private readonly Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('block-name', InputArgument::REQUIRED, 'Block class name (e.g., BookingsBlock)')
            ->addOption('component-name', 'c', InputOption::VALUE_OPTIONAL, 'Custom component name (defaults to block name without "Block" suffix)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing files')
            ->addOption('keep-block', null, InputOption::VALUE_NONE, 'Keep original block files (do not remove)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $blockName = $input->getArgument('block-name');
        $componentName = $input->getOption('component-name') ?? $this->deriveComponentName($blockName);
        $dryRun = $input->getOption('dry-run');
        $keepBlock = $input->getOption('keep-block');

        $io->title('Block to TwigComponent Converter');
        $io->info(\sprintf('Converting: %s → %s', $blockName, $componentName));

        // Validate block exists
        $blockPath = $this->projectDir.self::BLOCK_DIR.$blockName.'.php';
        if (!file_exists($blockPath)) {
            $io->error(\sprintf('Block file not found: %s', $blockPath));

            return Command::FAILURE;
        }

        // Read and parse block file
        $blockContent = file_get_contents($blockPath);
        if (false === $blockContent) {
            $io->error('Failed to read block file');

            return Command::FAILURE;
        }

        $io->section('Analyzing Block Structure');
        $blockInfo = $this->parseBlockClass($blockContent);

        if (null === $blockInfo) {
            $io->error('Failed to parse block structure');

            return Command::FAILURE;
        }

        $io->listing([
            \sprintf('Dependencies: %d found', \count($blockInfo['dependencies'])),
            \sprintf('Template: %s', $blockInfo['template'] ?? 'default'),
            \sprintf('Settings: %d found', \count($blockInfo['settings'])),
            \sprintf('Methods: %d found', \count($blockInfo['methods'])),
        ]);

        // Generate component class
        $io->section('Generating TwigComponent');
        $componentClassContent = $this->generateComponentClass($componentName, $blockInfo);
        $componentPath = $this->projectDir.self::COMPONENT_DIR.$componentName.'.php';

        // Find and convert template
        $templateConverted = false;
        if ($blockInfo['template']) {
            $blockTemplatePath = $this->projectDir.self::BLOCK_TEMPLATE_DIR.basename($blockInfo['template']);
            if (file_exists($blockTemplatePath)) {
                $io->section('Converting Template');
                $templateContent = file_get_contents($blockTemplatePath);
                if (false !== $templateContent) {
                    $componentTemplateContent = $this->convertTemplate($templateContent);
                    $componentTemplatePath = $this->projectDir.self::COMPONENT_TEMPLATE_DIR.$this->getTemplateFileName($componentName);

                    if ($dryRun) {
                        $io->writeln('<comment>[DRY RUN]</comment> Would create template: '.$componentTemplatePath);
                    } else {
                        $this->filesystem->dumpFile($componentTemplatePath, $componentTemplateContent);
                        $io->success('Template created: '.$componentTemplatePath);
                    }
                    $templateConverted = true;
                }
            } else {
                $io->warning(\sprintf('Block template not found: %s', $blockTemplatePath));
            }
        }

        // Write component class
        if ($dryRun) {
            $io->section('Preview: Component Class');
            $io->writeln($componentClassContent);
            $io->writeln('');
            $io->writeln('<comment>[DRY RUN]</comment> Would create: '.$componentPath);
        } else {
            $this->filesystem->dumpFile($componentPath, $componentClassContent);
            $io->success('Component created: '.$componentPath);
        }

        // Next steps
        $io->section('Next Steps');
        $io->listing([
            'Review and test the generated component',
            'Update the template in '.self::COMPONENT_TEMPLATE_DIR,
            'Replace block usage with: <twig:'.$componentName.' />',
            $keepBlock ? 'Original block kept (use --keep-block to remove)' : 'Remove block service registration from config/services.yaml',
            'Remove old block template from '.self::BLOCK_TEMPLATE_DIR.' if no longer needed',
        ]);

        if (!$keepBlock && !$dryRun) {
            $io->warning('Remember to remove block registration from config/services.yaml');
            $io->note(\sprintf('Search for: %s or %s', $blockName, strtolower(str_replace('Block', '', $blockName))));
        }

        return Command::SUCCESS;
    }

    private function deriveComponentName(string $blockName): string
    {
        // Remove "Block" suffix if present
        return preg_replace('/Block$/', '', $blockName) ?? $blockName;
    }

    private function getTemplateFileName(string $componentName): string
    {
        // Template name matches component class name (PascalCase)
        return $componentName.'.html.twig';
    }

    /**
     * Parse block class to extract relevant information.
     *
     * @return array<'dependencies'|'methods'|'template'|'settings', string[]|string|null>
     */
    private function parseBlockClass(string $content): array
    {
        $info = [
            'dependencies' => [],
            'template' => null,
            'methods' => [],
            'namespace' => 'App\\Block',
            'uses' => [],
            'settings' => [],
        ];

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $info['namespace'] = $matches[1];
        }

        // Extract use statements
        preg_match_all('/use\s+([^;]+);/', $content, $useMatches);
        $info['uses'] = $useMatches[1] ?? [];

        // Extract constructor dependencies
        if (preg_match('/public function __construct\([^)]*\)/', $content, $constructorMatch)) {
            preg_match_all('/(?:protected|private|public)\s+(?:readonly\s+)?([^\s]+)\s+\$([^,)]+)/', $constructorMatch[0], $paramMatches);

            if (isset($paramMatches[1]) && $paramMatches[1] !== []) {
                $counter = \count($paramMatches[1]);
                for ($i = 0; $i < $counter; ++$i) {
                    $type = $paramMatches[1][$i];
                    $name = $paramMatches[2][$i];

                    // Skip Twig environment as it's not needed in TwigComponent
                    if ('Environment' !== $type && 'twig' !== $name) {
                        $info['dependencies'][$name] = $type;
                    }
                }
            }
        }

        // Extract settings from configureSettings
        if (preg_match('/function configureSettings[^{]*{([^}]+)}/', $content, $settingsMatch)) {
            // Extract all setDefaults array
            if (preg_match('/setDefaults\(\s*\[([^\]]+)\]/', $settingsMatch[1], $defaultsMatch)) {
                // Parse key-value pairs
                preg_match_all('/[\'"]([^\'\"]+)[\'"]\s*=>\s*([^,\]]+)/', $defaultsMatch[1], $settingMatches);

                if (!empty($settingMatches[1])) {
                    for ($i = 0; $i < count($settingMatches[1]); $i++) {
                        $key = $settingMatches[1][$i];
                        $value = trim($settingMatches[2][$i]);

                        if ($key === 'template') {
                            // Extract template value without quotes
                            $info['template'] = trim($value, '\'"');
                        } else {
                            // Store other settings
                            $info['settings'][$key] = $value;
                        }
                    }
                }
            }
        }

        // Extract execute method logic (this will be moved to mount or helper methods)
        if (preg_match('/public function execute\([^{]*\)\s*:\s*Response\s*{([^}]+(?:{[^}]*}[^}]*)*)}/', $content, $executeMatch)) {
            $info['methods']['execute'] = $executeMatch[1];
        }

        // Extract helper methods (any method that's not execute, construct, configure*, getName, build*, validate*)
        preg_match_all('/(?:public|protected|private)\s+function\s+([a-zA-Z_]\w*)\s*\([^)]*\)[^{]*{/', $content, $methodMatches);
        foreach ($methodMatches[1] as $methodName) {
            // Try to extract the full method
            if (!\in_array($methodName, ['__construct', 'execute', 'configureSettings', 'getName', 'getBlockMetadata', 'buildCreateForm', 'buildEditForm', 'validateBlock']) && preg_match('/(?:public|protected|private)\s+function\s+'.preg_quote($methodName, '/').'\s*\([^)]*\)[^{]*{([^}]+(?:{[^}]*}[^}]*)*)}/', $content, $methodContentMatch)) {
                $info['methods'][$methodName] = $methodContentMatch[0];
            }
        }

        return $info;
    }

    /**
     * Generate TwigComponent class content.
     *
     * @param array{dependencies: array<string, string>, template: string|null, methods: array<string, string>, namespace: string, uses: array<string>, settings: array<string, string>} $blockInfo
     */
    private function generateComponentClass(string $componentName, array $blockInfo): string
    {
        $uses = [];
        $constructorParams = [];
        $publicProperties = [];
        $mountParams = [];
        $mountAssignments = [];

        // Add TwigComponent attribute use
        $uses[] = 'use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;';

        // Process dependencies
        foreach ($blockInfo['dependencies'] as $varName => $type) {
            $constructorParams[] = \sprintf('private readonly %s $%s', $type, $varName);

            // Add use statement for the type if it's not a built-in
            $fullType = $this->findFullTypeFromUses($type, $blockInfo['uses']);
            if ($fullType && !\in_array('use '.$fullType.';', $uses)) {
                $uses[] = 'use '.$fullType.';';
            }
        }

        // Process settings (block defaults become component properties and mount params)
        foreach ($blockInfo['settings'] as $settingName => $settingValue) {
            $phpType = $this->inferPhpType($settingValue);
            $defaultValue = $this->formatDefaultValue($settingValue, $phpType);

            // Add public property
            $publicProperties[] = \sprintf('public %s $%s = %s;', $phpType, $settingName, $defaultValue);

            // Add mount parameter
            $mountParams[] = \sprintf('%s $%s = %s', $phpType, $settingName, $defaultValue);

            // Add mount assignment
            $mountAssignments[] = \sprintf('$this->%s = $%s;', $settingName, $settingName);
        }

        // Build public properties block
        $propertiesBlock = '';
        if ($publicProperties !== []) {
            $propertiesBlock = "\n    " . implode("\n    ", $publicProperties) . "\n";
        }

        // Build constructor
        $constructor = '';
        if ($constructorParams !== []) {
            $constructor = <<<PHP

    public function __construct(
        {$this->indent(implode(",\n", $constructorParams), 2)}
    ) {}
PHP;
        }

        // Build mount method from execute logic if available
        $mountMethod = '';
        $mountSignature = $mountParams !== [] ? implode(', ', $mountParams) : '';

        if (!empty($blockInfo['methods']['execute'])) {
            $executeBody = $this->convertExecuteToMount($blockInfo['methods']['execute']);
            $assignmentsBlock = $mountAssignments !== [] ? "\n        " . implode("\n        ", $mountAssignments) . "\n" : '';

            $mountMethod = <<<PHP

    public function mount({$mountSignature}): void
    {{$assignmentsBlock}{$this->indent($executeBody, 2)}
    }
PHP;
        } elseif ($mountParams !== []) {
            // If no execute method but we have settings, create a simple mount
            $assignmentsBlock = implode("\n        ", $mountAssignments);
            $mountMethod = <<<PHP

    public function mount({$mountSignature}): void
    {
        {$assignmentsBlock}
    }
PHP;
        }

        // Add helper methods
        $helperMethods = '';
        foreach ($blockInfo['methods'] as $methodName => $methodContent) {
            if ('execute' !== $methodName) {
                $helperMethods .= "\n\n".$this->indent($methodContent, 1);
            }
        }

        // Build class content
        $usesString = implode("\n", $uses);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Twig\Components;

{$usesString}

#[AsTwigComponent]
final class {$componentName}
{{$propertiesBlock}{$constructor}{$mountMethod}{$helperMethods}
}

PHP;
    }

    private function inferPhpType(string $value): string
    {
        $trimmed = trim($value);

        // Boolean values
        if ($trimmed === 'true' || $trimmed === 'false') {
            return 'bool';
        }

        // Numeric values
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? 'float' : 'int';
        }

        // Array values
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }

        // Default to string
        return 'string';
    }

    private function formatDefaultValue(string $value, string $phpType): string
    {
        $trimmed = trim($value);

        // Already a PHP literal
        if ($phpType === 'bool' || $phpType === 'int' || $phpType === 'float') {
            return $trimmed;
        }

        // Array
        if ($phpType === 'array') {
            return $trimmed;
        }

        // String - ensure it's properly quoted
        if (str_starts_with($trimmed, "'") || str_starts_with($trimmed, '"')) {
            return $trimmed;
        }

        // Wrap in quotes
        return "'{$trimmed}'";
    }

    private function findFullTypeFromUses(string $shortType, array $uses): ?string
    {
        foreach ($uses as $use) {
            if (str_ends_with((string) $use, '\\'.$shortType)) {
                return $use;
            }
        }

        return null;
    }

    private function convertExecuteToMount(string $executeBody): string
    {
        // Remove renderResponse calls and extract variable assignments
        $cleaned = preg_replace('/return\s+\$this->renderResponse\([^;]+;/', '', $executeBody);

        // Convert $this->em to $this->em (should work as-is if em is injected)
        // Remove block context references
        $cleaned = preg_replace('/\$blockContext->getBlock\(\)/', '// TODO: Remove block context usage', (string) $cleaned);
        $cleaned = preg_replace('/\$blockContext->getTemplate\(\)/', '// TODO: Remove template reference', (string) $cleaned);

        // Add TODO comment for data storage
        $cleaned .= "\n        // TODO: Store data in public properties for template access\n        // Example: \$this->bookings = \$bookings;";

        return "\n".trim($cleaned)."\n    ";
    }

    /**
     * Convert block template to component template.
     */
    private function convertTemplate(string $content): string
    {
        // Remove sonata block base extension
        $converted = preg_replace('/{%\s*extends\s+sonata_block\.templates\.block_base\s*%}/', '', $content);

        // Remove {% block block %} wrapper and {% endblock %}
        $converted = preg_replace('/{%\s*block\s+block\s*%}/', '', (string) $converted);
        $converted = preg_replace('/{%\s*endblock\s*%}/', '', (string) $converted);

        // Convert variable references
        // Block templates use variables directly, components use 'this.'
        // This is a simple heuristic - may need manual adjustment
        $converted = preg_replace('/\{\{\s*([a-zA-Z_]\w*)\s*\}\}/', '{{ this.$1 }}', (string) $converted);
        $converted = preg_replace('/{%\s*for\s+([a-zA-Z_]\w*)\s+in\s+([a-zA-Z_]\w*)\s*%}/', '{% for $1 in this.$2 %}', (string) $converted);

        // Fix double 'this.this.' cases
        $converted = preg_replace('/this\.this\./', 'this.', (string) $converted);

        return trim((string) $converted)."\n";
    }

    private function indent(string $content, int $level = 1): string
    {
        $indent = str_repeat('    ', $level);
        $lines = explode("\n", $content);

        return implode("\n".$indent, $lines);
    }
}
