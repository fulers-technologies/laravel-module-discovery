<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeRegistryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * AttributeDiscoverCommand handles the Artisan command for automatic attribute discovery.
 * This command scans the configured modules directory for PHP classes with attributes,
 * extracts their attribute information, and registers them for efficient access.
 *
 * The command provides comprehensive attribute discovery with detailed output,
 * statistics, and error reporting for debugging and monitoring purposes.
 */
#[AsCommand(
    name: 'attribute:discover',
    description: 'Discover and register PHP attributes from classes in the modules directory',
    aliases: ['attributes:discover', 'discover:attributes']
)]
class AttributeDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Defines the command signature including the command name
     * and any optional parameters or flags.
     */
    protected $signature = 'attribute:discover
                            {--path= : Custom path to scan for attributes}
                            {--clear : Clear existing attributes before discovery}
                            {--format=table : Output format (table|json|summary)}
                            {--filter= : Filter by attribute type}
                            {--cache : Cache discovery results for performance}';

    /**
     * The console command description.
     * Provides a brief description of what the command does
     * for display in the Artisan command list.
     */
    protected $description = 'Discover and register PHP attributes from classes in the modules directory';

    /**
     * Creates a new AttributeDiscoverCommand instance.
     * Initializes the command with required dependencies for attribute discovery,
     * registry management, and configuration access.
     *
     * Parameters:
     *   - AttributeDiscoveryInterface $attributeDiscovery: Service for discovering attributes.
     *   - AttributeRegistryInterface $attributeRegistry: Service for registering attributes.
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly AttributeDiscoveryInterface $attributeDiscovery,
        private readonly AttributeRegistryInterface $attributeRegistry,
        private readonly ConfigurationInterface $configuration
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Performs the attribute discovery process including directory scanning,
     * attribute extraction, and registry registration.
     *
     * Returns:
     *   - int: Command exit code (0 for success, 1 for failure).
     */
    public function handle(): int
    {
        $this->info('🔍 Starting attribute discovery process...');

        try {
            $modulesPath = $this->getModulesPath();
            $shouldClear = $this->option('clear');
            $outputFormat = $this->option('format');
            $attributeFilter = $this->option('filter');
            $useCache = $this->option('cache');

            if ($this->isVerbose()) {
                $this->line("📁 Scanning directory: {$modulesPath}");
                if ($shouldClear) {
                    $this->line("🧹 Will clear existing attributes before discovery");
                }
                if ($attributeFilter) {
                    $this->line("🔍 Filtering by attribute type: {$attributeFilter}");
                }
                if ($useCache) {
                    $this->line("💾 Will cache discovery results");
                }
            }

            // Clear existing attributes if requested
            if ($shouldClear) {
                $this->info('🧹 Clearing existing attribute registry...');
                $clearSuccess = $this->attributeRegistry->clearRegistry();

                if ($clearSuccess) {
                    $this->info('✅ Registry cleared successfully');
                } else {
                    $this->warn('⚠️  Failed to clear registry, continuing with discovery');
                }
            }

            // Check cache first if enabled
            $discoveredAttributes = null;
            if ($useCache) {
                $cacheKey = 'attribute_discovery_' . md5($modulesPath . $attributeFilter);
                $discoveredAttributes = Cache::get($cacheKey);

                if ($discoveredAttributes) {
                    $this->info('💾 Using cached discovery results');
                }
            }

            // Discover attributes if not cached
            if (!$discoveredAttributes) {
                $this->info('🔍 Discovering attributes...');
                $discoveredAttributes = $this->attributeDiscovery->discoverAttributes($modulesPath);

                // Cache results if enabled
                if ($useCache && !empty($discoveredAttributes)) {
                    $cacheKey = 'attribute_discovery_' . md5($modulesPath . $attributeFilter);
                    $cacheTtl = $this->configuration->get('attribute-discovery.cache.ttl', 3600);
                    Cache::put($cacheKey, $discoveredAttributes, $cacheTtl);
                    $this->info('💾 Discovery results cached');
                }
            }

            if (empty($discoveredAttributes)) {
                $this->warn('⚠️  No attributes found in the specified directory.');
                $this->displaySuggestedActions();
                return 0;
            }

            $this->info("✅ Discovered attributes in " . count($discoveredAttributes) . " classes");

            // Filter attributes if requested
            if ($attributeFilter) {
                $discoveredAttributes = $this->filterAttributesByType($discoveredAttributes, $attributeFilter);
                $this->info("🔍 Filtered to " . count($discoveredAttributes) . " classes with '{$attributeFilter}' attributes");
            }

            // Validate attributes
            $this->info('🔍 Validating discovered attributes...');
            $validatedAttributes = $this->attributeDiscovery->validateAttributes($discoveredAttributes);

            if (count($validatedAttributes) < count($discoveredAttributes)) {
                $filtered = count($discoveredAttributes) - count($validatedAttributes);
                $this->warn("⚠️  {$filtered} classes filtered out during validation");
            }

            // Register attributes
            $this->info('🔧 Registering attributes...');
            $registrationSuccess = $this->attributeRegistry->registerAttributes($validatedAttributes);

            if ($registrationSuccess) {
                $this->info('🎉 Attribute discovery completed successfully!');

                // Display results
                $this->displayResults($validatedAttributes, $outputFormat);

                // Display statistics
                $this->displayStatistics();

                return 0;
            } else {
                $this->error('❌ Failed to register discovered attributes');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("💥 Attribute discovery failed: " . $e->getMessage());

            if ($this->isVerbose() || $this->configuration->isDebugModeEnabled()) {
                $this->line('');
                $this->line('🔍 Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Gets the modules directory path to scan.
     * Determines the directory path based on command options,
     * configuration settings, or falls back to the default.
     *
     * Returns:
     *   - string: The absolute path to the modules directory.
     */
    private function getModulesPath(): string
    {
        $customPath = $this->option('path');

        if ($customPath !== null) {
            return $this->isAbsolutePath($customPath)
                ? $customPath
                : base_path($customPath);
        }

        $configuredPath = $this->configuration->getDefaultModulesDirectory();
        return base_path($configuredPath);
    }

    /**
     * Checks if verbose output is enabled.
     * Uses Laravel's built-in verbose option checking and configuration
     * settings to determine if detailed output should be displayed.
     *
     * Returns:
     *   - bool: True if verbose output is enabled, false otherwise.
     */
    private function isVerbose(): bool
    {
        return $this->getOutput()->isVerbose() || $this->configuration->isDebugModeEnabled();
    }

    /**
     * Filters attributes by type.
     * Filters the discovered attributes to only include classes
     * that have attributes of the specified type.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The attributes to filter.
     *   - string $attributeType: The attribute type to filter by.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: Filtered attributes.
     */
    private function filterAttributesByType(array $attributes, string $attributeType): array
    {
        return Collection::make($attributes)
            ->filter(fn($classAttributes) => $this->classHasAttributeType($classAttributes, $attributeType))
            ->toArray();
    }

    /**
     * Checks if a class has a specific attribute type.
     * Searches through class attributes to determine if the class
     * has an attribute of the specified type.
     *
     * Parameters:
     *   - array<string, mixed> $classAttributes: The class attributes to search.
     *   - string $attributeType: The attribute type to search for.
     *
     * Returns:
     *   - bool: True if the class has the attribute type, false otherwise.
     */
    private function classHasAttributeType(array $classAttributes, string $attributeType): bool
    {
        foreach ($classAttributes as $attributeCategory) {
            if (is_array($attributeCategory)) {
                foreach ($attributeCategory as $attribute) {
                    if (is_array($attribute) && isset($attribute['name'])) {
                        if (str_contains($attribute['name'], $attributeType)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Displays the discovery results in the specified format.
     * Shows information about discovered attributes in table,
     * JSON, or summary format based on user preference.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The discovered attributes.
     *   - string $format: The output format (table|json|summary).
     */
    private function displayResults(array $attributes, string $format): void
    {
        $this->line('');

        switch ($format) {
            case 'json':
                $this->displayJsonResults($attributes);
                break;
            case 'summary':
                $this->displaySummaryResults($attributes);
                break;
            case 'table':
            default:
                $this->displayTableResults($attributes);
                break;
        }
    }

    /**
     * Displays results in table format.
     * Shows discovered attributes in a formatted table with
     * class names, attribute types, and counts.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The discovered attributes.
     */
    private function displayTableResults(array $attributes): void
    {
        $this->info('📋 Discovered Attributes:');

        $tableData = Collection::make($attributes)
            ->map(function ($classAttributes, $className) {
                return [
                    'class' => $className,
                    'attributes' => $this->countClassAttributes($classAttributes),
                    'types' => implode(', ', $this->getClassAttributeTypes($classAttributes)),
                ];
            })
            ->values()
            ->toArray();

        $this->table(['Class', 'Attributes', 'Types'], $tableData);
    }

    /**
     * Displays results in JSON format.
     * Shows discovered attributes as formatted JSON output
     * for programmatic consumption or detailed inspection.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The discovered attributes.
     */
    private function displayJsonResults(array $attributes): void
    {
        $this->info('📋 Discovered Attributes (JSON):');
        $this->line(json_encode($attributes, JSON_PRETTY_PRINT));
    }

    /**
     * Displays results in summary format.
     * Shows a high-level summary of discovered attributes
     * with counts and statistics.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The discovered attributes.
     */
    private function displaySummaryResults(array $attributes): void
    {
        $this->info('📊 Discovery Summary:');

        $totalClasses = count($attributes);
        $totalAttributes = 0;
        $attributeTypeStats = [];

        foreach ($attributes as $classAttributes) {
            $totalAttributes += $this->countClassAttributes($classAttributes);

            foreach ($this->getClassAttributeTypes($classAttributes) as $type) {
                $attributeTypeStats[$type] = Arr::get($attributeTypeStats, $type, 0) + 1;
            }
        }

        $this->line("  📦 Classes with attributes: {$totalClasses}");
        $this->line("  🏷️  Total attributes found: {$totalAttributes}");
        $this->line("  📈 Average per class: " . round($totalAttributes / max($totalClasses, 1), 2));

        if (!empty($attributeTypeStats)) {
            $this->line('');
            $this->line('🏷️  Attribute types:');
            foreach ($attributeTypeStats as $type => $count) {
                $this->line("    • {$type}: {$count} classes");
            }
        }
    }

    /**
     * Displays discovery and registry statistics.
     * Shows detailed statistics about the discovery process
     * and registry operations for monitoring and debugging.
     */
    private function displayStatistics(): void
    {
        if (!$this->isVerbose()) {
            return;
        }

        $this->line('');
        $this->info('📊 Discovery Statistics:');

        // Get discovery statistics
        $discoveryStatus = $this->attributeDiscovery->getDiscoveryStatus();
        if (isset($discoveryStatus['statistics'])) {
            $stats = $discoveryStatus['statistics'];

            $this->line("  📄 Processed classes: " . Arr::get($stats, 'processed_classes', 0));
            $this->line("  ⏱️  Processing time: " . round(Arr::get($stats, 'processing_time', 0), 3) . 's');
            $this->line("  🎯 Classes with attributes: " . Arr::get($stats, 'discovered_attributes', 0));

            $errorClasses = Arr::get($stats, 'error_classes', []);
            if (!empty($errorClasses)) {
                $this->line("  ❌ Classes with errors: " . count($errorClasses));
            }
        }

        // Get registry statistics
        $registryStats = $this->attributeRegistry->getRegistryStats();
        $this->line('');
        $this->info('📊 Registry Statistics:');
        $this->line("  📦 Total registered classes: " . Arr::get($registryStats, 'total_classes', 0));
        $this->line("  🏷️  Total attributes: " . Arr::get($registryStats, 'total_attributes', 0));
        $this->line("  💾 Storage type: " . Arr::get($registryStats, 'storage_type', 'unknown'));
        $this->line("  📏 Registry size: " . $this->formatBytes(Arr::get($registryStats, 'registry_size_bytes', 0)));
    }

    /**
     * Displays suggested actions when no attributes are found.
     * Shows helpful suggestions to users when attribute discovery
     * doesn't find any attributes in the specified directory.
     */
    private function displaySuggestedActions(): void
    {
        $this->line('');
        $this->line('💡 Suggested actions:');
        $this->line('  • Check if your classes have PHP 8+ attributes');
        $this->line('  • Verify the modules directory path');
        $this->line('  • Use --path option to specify a different directory');
        $this->line('  • Use --verbose flag for detailed output');
        $this->line('');
        $this->line('Example attributes to look for:');
        $this->line('  • #[Route(...)]');
        $this->line('  • #[Middleware(...)]');
        $this->line('  • #[Validate(...)]');
        $this->line('  • Custom attributes in your application');
    }

    /**
     * Counts the total number of attributes in a class.
     * Calculates the total count of all attributes across all
     * attribute categories for a specific class.
     *
     * Parameters:
     *   - array<string, mixed> $classAttributes: The class attributes to count.
     *
     * Returns:
     *   - int: The total number of attributes.
     */
    private function countClassAttributes(array $classAttributes): int
    {
        return Collection::make($classAttributes)
            ->filter(fn($item) => is_array($item))
            ->sum(fn($item) => count($item));
    }

    /**
     * Gets the unique attribute types for a class.
     * Extracts all unique attribute type names from
     * the class attributes for display purposes.
     *
     * Parameters:
     *   - array<string, mixed> $classAttributes: The class attributes to analyze.
     *
     * Returns:
     *   - array<string>: Array of unique attribute type names.
     */
    private function getClassAttributeTypes(array $classAttributes): array
    {
        $types = [];

        foreach ($classAttributes as $attributeCategory) {
            if (is_array($attributeCategory)) {
                foreach ($attributeCategory as $attribute) {
                    if (is_array($attribute) && isset($attribute['name'])) {
                        $shortName = class_basename($attribute['name']);
                        $types[] = $shortName;
                    }
                }
            }
        }

        return array_unique($types);
    }

    /**
     * Formats bytes into human-readable format.
     * Converts byte values into readable format with appropriate
     * units (B, KB, MB, GB) for display purposes.
     *
     * Parameters:
     *   - int $bytes: The number of bytes to format.
     *
     * Returns:
     *   - string: The formatted byte string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Checks if a path is absolute.
     * Determines whether the provided path is absolute and does not
     * require base path resolution for proper directory access.
     *
     * Parameters:
     *   - string $path: The path to check.
     *
     * Returns:
     *   - bool: True if the path is absolute, false otherwise.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path);
    }
}
