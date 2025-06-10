<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use LaravelModuleDiscovery\ComposerHook\Exceptions\DirectoryNotFoundException;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * ModuleDiscoverCommand handles the Artisan command for automatic module discovery.
 * This command scans the configured modules directory for PHP classes, extracts
 * their namespaces, and registers them with Composer's autoloader for automatic loading.
 *
 * The command is designed to be triggered by Composer hooks during install,
 * update, and autoload dump operations to maintain current autoloader registrations.
 */
#[AsCommand(
    name: 'module:discover',
    description: 'Discover and register module namespaces for Composer autoloading',
    aliases: ['modules:discover', 'discover:modules']
)]
class ModuleDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Defines the command signature including the command name
     * and any optional parameters or flags.
     */
    protected $signature = 'module:discover
                            {--path= : Custom path to scan for modules}
                            {--dry-run : Run discovery without registering namespaces}
                            {--update-composer : Update composer.json with discovered namespaces}
                            {--clear-cache : Clear discovery cache before scanning}';

    /**
     * The console command description.
     * Provides a brief description of what the command does
     * for display in the Artisan command list.
     */
    protected $description = 'Discover and register module namespaces for Composer autoloading';

    /**
     * Creates a new ModuleDiscoverCommand instance.
     * Initializes the command with required dependencies for class discovery,
     * Composer autoloader integration, and configuration management.
     *
     * Parameters:
     *   - ClassDiscoveryInterface $classDiscovery: Service for discovering classes in directories.
     *   - ComposerLoaderInterface $composerLoader: Service for registering namespaces with Composer.
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly ClassDiscoveryInterface $classDiscovery,
        private readonly ComposerLoaderInterface $composerLoader,
        private readonly ConfigurationInterface $configuration
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Performs the module discovery process including directory scanning,
     * namespace extraction, and autoloader registration.
     *
     * Returns:
     *   - int: Command exit code (0 for success, 1 for failure).
     */
    public function handle(): int
    {
        $this->info('🔍 Starting module discovery process...');

        try {
            $modulesPath    = $this->getModulesPath();
            $isDryRun       = $this->option('dry-run') || $this->configuration->isDryRunModeEnabled();
            $updateComposer = $this->option('update-composer');
            $clearCache     = $this->option('clear-cache');

            if ($this->isVerbose()) {
                $this->line("📁 Scanning directory: {$modulesPath}");
                if ($isDryRun) {
                    $this->line("🧪 Running in dry-run mode - no namespaces will be registered");
                }
                if ($updateComposer) {
                    $this->line("📝 Will update composer.json with discovered namespaces");
                }
                if ($clearCache) {
                    $this->line("🧹 Will clear discovery cache before scanning");
                }
            }

            // Clear cache if requested
            if ($clearCache) {
                $this->clearDiscoveryCache();
            }

            // Discover classes and namespaces
            $discoveredClasses = $this->classDiscovery->discoverClasses($modulesPath);

            if (empty($discoveredClasses)) {
                $this->warn('⚠️  No modules found in the specified directory.');
                $this->displaySuggestedDirectories();
                return 0;
            }

            $this->info("✅ Discovered " . count($discoveredClasses) . " module namespaces");

            if ($this->isVerbose()) {
                $this->line('');
                $this->line('📋 Discovered namespaces:');
                foreach ($discoveredClasses as $namespace => $path) {
                    $this->line("  • {$namespace} => {$path}");
                }
                $this->line('');
            }

            // Register namespaces with Composer (unless dry run)
            $registrationResults = [];
            $applicationSuccess  = true;

            if (! $isDryRun && $this->configuration->isAutoRegisterNamespacesEnabled()) {
                $this->info('🔧 Registering namespaces with Composer autoloader...');

                $registrationResults = $this->composerLoader->registerMultipleNamespaces($discoveredClasses);
                $applicationSuccess  = $this->configuration->isAutoApplyRegistrationsEnabled()
                ? $this->composerLoader->applyRegistrations()
                : true;

                // Show registration results
                $successCount = count(array_filter($registrationResults));
                $this->info("✅ Registered {$successCount}/" . count($discoveredClasses) . " namespaces successfully");

                if ($applicationSuccess) {
                    $this->info('🎉 Module discovery completed successfully!');

                    // Suggest running composer dump-autoload for persistence
                    $this->line('');
                    $this->info('💡 To make these changes permanent, run:');
                    $this->line('   composer dump-autoload');

                } else {
                    $this->warn('⚠️  Some registrations may not be active due to application errors.');
                }

            } else {
                // In dry run mode, simulate successful registration
                $registrationResults = array_fill_keys(array_keys($discoveredClasses), true);
                $this->info('🧪 Dry run completed - no actual registration performed');
            }

            // Display detailed results if verbose
            if ($this->isVerbose()) {
                $this->displayDetailedResults($discoveredClasses, $registrationResults, $isDryRun);
            }

            // Display discovery statistics
            $this->displayDiscoveryStatistics();

            return $this->determineExitCode($registrationResults, $applicationSuccess);

        } catch (DirectoryNotFoundException $e) {
            $this->error("❌ " . $e->getMessage());

            if (! empty($e->getSuggestions())) {
                $this->line('');
                $this->line('💡 Suggested directories:');
                foreach ($e->getSuggestions() as $suggestion) {
                    $this->line("  • {$suggestion}");
                }
            }

            return 1;

        } catch (ModuleDiscoveryException $e) {
            $this->error("❌ Module discovery failed: " . $e->getMessage());

            if ($this->isVerbose() && $e->getFailedPath()) {
                $this->line("📁 Failed path: {$e->getFailedPath()}");
            }

            return 1;

        } catch (\Exception $e) {
            $this->error("💥 Unexpected error during module discovery: " . $e->getMessage());

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
     * Clears the discovery cache.
     * Removes cached discovery results to ensure fresh scanning
     * when the clear-cache option is used.
     */
    private function clearDiscoveryCache(): void
    {
        $this->info('🧹 Clearing discovery cache...');

        try {
            // Clear Laravel cache
            Cache::forget('module_discovery_results');
            Cache::forget('namespace_extraction_cache');

            // Clear file-based cache if it exists
            $cacheDir = storage_path('framework/cache/module-discovery');
            if (File::exists($cacheDir)) {
                File::deleteDirectory($cacheDir);
            }

            $this->info('✅ Discovery cache cleared successfully');

        } catch (\Exception $e) {
            $this->warn("⚠️  Failed to clear cache: " . $e->getMessage());
        }
    }

    /**
     * Displays detailed results of the discovery process.
     * Shows individual namespace registrations and their success status
     * when verbose output is requested.
     *
     * Parameters:
     *   - array<string, string> $discoveredClasses: The discovered namespace-to-path mappings.
     *   - array<string, bool> $registrationResults: Results of namespace registration attempts.
     *   - bool $isDryRun: Whether this was a dry run operation.
     */
    private function displayDetailedResults(
        array $discoveredClasses,
        array $registrationResults,
        bool $isDryRun
    ): void {
        $this->line('');
        $this->line($isDryRun ? '🧪 Discovered namespaces (would be registered):' : '📝 Registration results:');

        foreach ($discoveredClasses as $namespace => $path) {
            $status     = $registrationResults[$namespace] ?? false;
            $statusText = $status ? '<info>✅</info>' : '<error>❌</error>';

            if ($isDryRun) {
                $statusText = '<comment>🧪</comment>'; // Indicate dry run
            }

            $this->line("  {$statusText} {$namespace}");
            if ($this->isVerbose()) {
                $this->line("     📁 {$path}");
            }
        }
    }

    /**
     * Displays discovery statistics and performance information.
     * Shows detailed statistics about the discovery process including
     * processing time, file counts, and any errors encountered.
     */
    private function displayDiscoveryStatistics(): void
    {
        if (! $this->isVerbose()) {
            return;
        }

        $discoveryStatus = $this->classDiscovery->getDiscoveryStatus();

        if (isset($discoveryStatus['statistics'])) {
            $stats = $discoveryStatus['statistics'];

            $this->line('');
            $this->line('📊 Discovery Statistics:');
            $this->line("  📄 Processed files: " . Arr::get($stats, 'processed_files', 0));
            $this->line("  ⏱️  Processing time: " . round(Arr::get($stats, 'processing_time', 0), 3) . 's');
            $this->line("  🎯 Namespaces found: " . Arr::get($stats, 'discovered_namespaces', 0));

            $errorFiles = Arr::get($stats, 'error_files', []);
            if (! empty($errorFiles)) {
                $this->line("  ❌ Files with errors: " . count($errorFiles));

                foreach ($errorFiles as $errorFile) {
                    $this->line("    • {$errorFile['file']}: {$errorFile['error']}");
                }
            }
        }
    }

    /**
     * Displays suggested directories when modules directory is not found.
     * Shows a list of common directory names that might contain modules
     * to help users identify alternative locations.
     */
    private function displaySuggestedDirectories(): void
    {
        $suggestions = $this->configuration->getSuggestedDirectories();

        if (! empty($suggestions)) {
            $this->line('');
            $this->line('💡 Suggested module directories to create:');
            foreach ($suggestions as $suggestion) {
                $this->line("  • {$suggestion}");
            }
        }
    }

    /**
     * Determines the appropriate exit code based on registration results.
     * Analyzes the success/failure status of namespace registrations
     * to determine if the command should exit with success or error code.
     *
     * Parameters:
     *   - array<string, bool> $registrationResults: Results of namespace registration attempts.
     *   - bool $applicationSuccess: Whether the final application of registrations succeeded.
     *
     * Returns:
     *   - int: Exit code (0 for success, 1 for failure).
     */
    private function determineExitCode(array $registrationResults, bool $applicationSuccess): int
    {
        // If no registrations were attempted, consider it success
        if (empty($registrationResults)) {
            return 0;
        }

        // Check if all registrations succeeded
        $allSuccessful = ! in_array(false, $registrationResults, true);

        return ($allSuccessful && $applicationSuccess) ? 0 : 1;
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
