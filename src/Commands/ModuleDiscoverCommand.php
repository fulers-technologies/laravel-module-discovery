<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use LaravelModuleDiscovery\ComposerHook\Exceptions\DirectoryNotFoundException;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;

/**
 * ModuleDiscoverCommand handles the Artisan command for automatic module discovery.
 * This command scans the configured modules directory for PHP classes, extracts
 * their namespaces, and registers them with Composer's autoloader for automatic loading.
 *
 * The command is designed to be triggered by Composer hooks during install,
 * update, and autoload dump operations to maintain current autoloader registrations.
 */
class ModuleDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Defines the command signature including the command name
     * and any optional parameters or flags.
     */
    protected $signature = 'module:discover {--path= : Custom path to scan for modules} {--dry-run : Run discovery without registering namespaces}';

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
        $this->info('Starting module discovery process...');

        try {
            $modulesPath = $this->getModulesPath();
            $isDryRun    = $this->option('dry-run') || $this->configuration->isDryRunModeEnabled();

            if ($this->isVerbose()) {
                $this->line("Scanning directory: {$modulesPath}");
                if ($isDryRun) {
                    $this->line("Running in dry-run mode - no namespaces will be registered");
                }
            }

            // Discover classes and namespaces
            $discoveredClasses = $this->classDiscovery->discoverClasses($modulesPath);

            if (empty($discoveredClasses)) {
                $this->warn('No modules found in the specified directory.');
                $this->displaySuggestedDirectories();
                return 0;
            }

            // Register namespaces with Composer (unless dry run)
            $registrationResults = [];
            $applicationSuccess  = true;

            if (! $isDryRun && $this->configuration->isAutoRegisterNamespacesEnabled()) {
                $registrationResults = $this->composerLoader->registerMultipleNamespaces($discoveredClasses);
                $applicationSuccess  = $this->configuration->isAutoApplyRegistrationsEnabled()
                ? $this->composerLoader->applyRegistrations()
                : true;
            } else {
                // In dry run mode, simulate successful registration
                $registrationResults = array_fill_keys(array_keys($discoveredClasses), true);
            }

            // Display results
            $this->displayResults($discoveredClasses, $registrationResults, $applicationSuccess, $isDryRun);

            return $this->determineExitCode($registrationResults, $applicationSuccess);

        } catch (DirectoryNotFoundException $e) {
            $this->error($e->getMessage());

            if (! empty($e->getSuggestions())) {
                $this->line('Suggested directories:');
                foreach ($e->getSuggestions() as $suggestion) {
                    $this->line("  - {$suggestion}");
                }
            }

            return 1;

        } catch (ModuleDiscoveryException $e) {
            $this->error("Module discovery failed: {$e->getMessage()}");

            if ($this->isVerbose() && $e->getFailedPath()) {
                $this->line("Failed path: {$e->getFailedPath()}");
            }

            return 1;

        } catch (\Exception $e) {
            $this->error("Unexpected error during module discovery: {$e->getMessage()}");

            if ($this->isVerbose() || $this->configuration->isDebugModeEnabled()) {
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
     * Displays the results of the discovery and registration process.
     * Shows information about discovered namespaces, registration results,
     * and any errors encountered during the process.
     *
     * Parameters:
     *   - array<string, string> $discoveredClasses: The discovered namespace-to-path mappings.
     *   - array<string, bool> $registrationResults: Results of namespace registration attempts.
     *   - bool $applicationSuccess: Whether the final application of registrations succeeded.
     *   - bool $isDryRun: Whether this was a dry run operation.
     */
    private function displayResults(
        array $discoveredClasses,
        array $registrationResults,
        bool $applicationSuccess,
        bool $isDryRun
    ): void {
        $successCount = count(array_filter($registrationResults));
        $totalCount   = count($discoveredClasses);

        if ($isDryRun) {
            $this->info("Module discovery completed (dry run)!");
            $this->line("Discovered {$totalCount} namespaces that would be registered.");
        } else {
            $this->info("Module discovery completed successfully!");
            $this->line("Discovered {$totalCount} namespaces, registered {$successCount} successfully.");
        }

        if ($this->isVerbose()) {
            $this->displayDetailedResults($discoveredClasses, $registrationResults, $isDryRun);
        }

        if (! $isDryRun && ! $applicationSuccess) {
            $this->warn('Warning: Some registrations may not be active due to application errors.');
        }

        // Display discovery statistics
        $this->displayDiscoveryStatistics();
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
        $this->line($isDryRun ? 'Discovered namespaces (would be registered):' : 'Registered namespaces:');

        foreach ($discoveredClasses as $namespace => $path) {
            $status     = $registrationResults[$namespace] ?? false;
            $statusText = $status ? '<info>✓</info>' : '<error>✗</error>';

            if ($isDryRun) {
                $statusText = '<comment>~</comment>'; // Indicate dry run
            }

            $this->line("  {$statusText} {$namespace} => {$path}");
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
            $this->line('Discovery Statistics:');
            $this->line("  Processed files: " . ($stats['processed_files'] ?? 0));
            $this->line("  Processing time: " . round($stats['processing_time'] ?? 0, 3) . 's');

            if (! empty($stats['error_files'])) {
                $this->line("  Files with errors: " . count($stats['error_files']));

                foreach ($stats['error_files'] as $errorFile) {
                    $this->line("    - {$errorFile['file']}: {$errorFile['error']}");
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
            $this->line('Suggested module directories to create:');
            foreach ($suggestions as $suggestion) {
                $this->line("  - {$suggestion}");
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
