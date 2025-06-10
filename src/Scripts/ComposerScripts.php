<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Scripts;

use Composer\Script\Event;
use LaravelModuleDiscovery\ComposerHook\Services\ClassDiscoveryService;
use LaravelModuleDiscovery\ComposerHook\Services\ComposerLoaderService;
use LaravelModuleDiscovery\ComposerHook\Services\ConfigurationService;

/**
 * ComposerScripts provides automatic module discovery during Composer operations.
 * This class integrates with Composer's event system to automatically discover
 * and register module namespaces during install, update, and dump-autoload operations.
 *
 * The scripts eliminate the need for manual command execution by hooking into
 * Composer's lifecycle events and performing discovery operations automatically.
 */
class ComposerScripts
{
    /**
     * Handles post-install-cmd Composer event.
     * Automatically discovers and registers module namespaces after
     * package installation to ensure new modules are immediately available.
     *
     * Parameters:
     *   - Event $event: The Composer event instance containing context information.
     */
    public static function postInstall(Event $event): void
    {
        static::runModuleDiscovery($event, 'post-install-cmd');
    }

    /**
     * Handles post-update-cmd Composer event.
     * Automatically discovers and registers module namespaces after
     * package updates to ensure module changes are properly reflected.
     *
     * Parameters:
     *   - Event $event: The Composer event instance containing context information.
     */
    public static function postUpdate(Event $event): void
    {
        static::runModuleDiscovery($event, 'post-update-cmd');
    }

    /**
     * Handles post-autoload-dump Composer event.
     * Automatically discovers and registers module namespaces after
     * autoload dump operations to maintain current registrations.
     *
     * Parameters:
     *   - Event $event: The Composer event instance containing context information.
     */
    public static function postAutoloadDump(Event $event): void
    {
        static::runModuleDiscovery($event, 'post-autoload-dump');
    }

    /**
     * Runs the module discovery process during Composer events.
     * Performs the actual module discovery and registration operations
     * with appropriate error handling and output formatting.
     *
     * Parameters:
     *   - Event $event: The Composer event instance.
     *   - string $eventName: The name of the Composer event being handled.
     */
    private static function runModuleDiscovery(Event $event, string $eventName): void
    {
        $io = $event->getIO();

        try {
            // Check if we're in a Laravel project
            if (!static::isLaravelProject()) {
                $io->writeError('⚠️  Laravel Module Discovery: Not a Laravel project, skipping module discovery');
                return;
            }

            $io->write('🔍 <info>Laravel Module Discovery:</info> Starting automatic module discovery...');

            // Initialize services
            $configuration = ConfigurationService::make();
            $classDiscovery = ClassDiscoveryService::make();
            $composerLoader = ComposerLoaderService::make();

            // Get modules directory
            $modulesPath = static::getModulesPath($configuration);

            if (!is_dir($modulesPath)) {
                $io->write("⚠️  <comment>Laravel Module Discovery:</comment> Modules directory '{$modulesPath}' not found, skipping discovery");
                return;
            }

            // Discover classes
            $discoveredClasses = $classDiscovery->discoverClasses($modulesPath);

            if (empty($discoveredClasses)) {
                $io->write('ℹ️  <comment>Laravel Module Discovery:</comment> No modules found to register');
                return;
            }

            $io->write("✅ <info>Laravel Module Discovery:</info> Discovered " . count($discoveredClasses) . " module namespaces");

            // Register with Composer autoloader
            if ($configuration->isAutoRegisterNamespacesEnabled()) {
                $registrationResults = $composerLoader->registerMultipleNamespaces($discoveredClasses);
                $successCount = count(array_filter($registrationResults));

                if ($successCount > 0) {
                    $composerLoader->applyRegistrations();
                    $io->write("🎉 <info>Laravel Module Discovery:</info> Registered {$successCount}/{count($discoveredClasses)} namespaces successfully");

                    // Show discovered namespaces in verbose mode
                    if ($io->isVerbose()) {
                        $io->write('📋 <info>Registered namespaces:</info>');
                        foreach ($discoveredClasses as $namespace => $path) {
                            $success = $registrationResults[$namespace] ?? false;
                            $status = $success ? '✅' : '❌';
                            $io->write("   {$status} {$namespace}");
                        }
                    }
                } else {
                    $io->writeError('❌ <error>Laravel Module Discovery:</error> Failed to register any namespaces');
                }
            } else {
                $io->write('ℹ️  <comment>Laravel Module Discovery:</comment> Auto-registration disabled in configuration');
            }

        } catch (\Exception $e) {
            $io->writeError("❌ <error>Laravel Module Discovery Error:</error> " . $e->getMessage());

            if ($io->isVerbose()) {
                $io->writeError('🔍 <comment>Stack trace:</comment>');
                $io->writeError($e->getTraceAsString());
            }
        }
    }

    /**
     * Checks if the current project is a Laravel application.
     * Determines whether module discovery should be performed by
     * checking for Laravel-specific files and structure.
     *
     * Returns:
     *   - bool: True if this is a Laravel project, false otherwise.
     */
    private static function isLaravelProject(): bool
    {
        // Check for Laravel-specific files
        $laravelIndicators = [
            'artisan',
            'app/Http/Kernel.php',
            'bootstrap/app.php',
            'config/app.php',
        ];

        foreach ($laravelIndicators as $indicator) {
            if (file_exists(getcwd() . '/' . $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the modules directory path for discovery.
     * Determines the appropriate modules directory based on
     * configuration settings and project structure.
     *
     * Parameters:
     *   - ConfigurationService $configuration: The configuration service instance.
     *
     * Returns:
     *   - string: The absolute path to the modules directory.
     */
    private static function getModulesPath(ConfigurationService $configuration): string
    {
        $defaultPath = $configuration->getDefaultModulesDirectory();
        $basePath = getcwd();

        return $basePath . DIRECTORY_SEPARATOR . $defaultPath;
    }

    /**
     * Handles discovery with error recovery.
     * Provides robust error handling and recovery mechanisms
     * for module discovery operations during Composer events.
     *
     * Parameters:
     *   - Event $event: The Composer event instance.
     *   - callable $discoveryCallback: The discovery operation to perform.
     */
    private static function handleDiscoveryWithRecovery(Event $event, callable $discoveryCallback): void
    {
        $io = $event->getIO();
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $discoveryCallback();
                return; // Success, exit retry loop

            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    // Final attempt failed, report error
                    $io->writeError("❌ <error>Laravel Module Discovery:</error> Failed after {$maxRetries} attempts: " . $e->getMessage());
                    return;
                }

                // Retry with delay
                $io->write("⚠️  <comment>Laravel Module Discovery:</comment> Attempt {$attempt} failed, retrying in {$retryDelay}s...");
                sleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
            }
        }
    }

    /**
     * Gets the configuration for Composer script execution.
     * Returns configuration settings specific to Composer script
     * execution context and environment.
     *
     * Returns:
     *   - array<string, mixed>: Configuration array for script execution.
     */
    private static function getScriptConfiguration(): array
    {
        return [
            'timeout' => 60, // 1 minute timeout for discovery
            'memory_limit' => '256M',
            'error_reporting' => E_ERROR | E_WARNING,
            'display_errors' => false,
        ];
    }

    /**
     * Sets up the execution environment for module discovery.
     * Configures PHP settings and environment variables for
     * optimal discovery performance during Composer operations.
     *
     * Parameters:
     *   - array<string, mixed> $config: Configuration settings to apply.
     */
    private static function setupExecutionEnvironment(array $config): void
    {
        // Set memory limit
        if (isset($config['memory_limit'])) {
            ini_set('memory_limit', $config['memory_limit']);
        }

        // Set error reporting
        if (isset($config['error_reporting'])) {
            error_reporting($config['error_reporting']);
        }

        // Set display errors
        if (isset($config['display_errors'])) {
            ini_set('display_errors', $config['display_errors'] ? '1' : '0');
        }

        // Set execution timeout
        if (isset($config['timeout'])) {
            set_time_limit($config['timeout']);
        }
    }

    /**
     * Validates the execution environment for module discovery.
     * Checks that all required dependencies and conditions are met
     * before attempting module discovery operations.
     *
     * Returns:
     *   - bool: True if environment is valid, false otherwise.
     */
    private static function validateExecutionEnvironment(): bool
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            return false;
        }

        // Check required extensions
        $requiredExtensions = ['json', 'mbstring'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1' && static::parseMemoryLimit($memoryLimit) < 128 * 1024 * 1024) {
            return false;
        }

        return true;
    }

    /**
     * Parses memory limit string to bytes.
     * Converts PHP memory limit strings (like "128M") to byte values
     * for comparison and validation purposes.
     *
     * Parameters:
     *   - string $memoryLimit: The memory limit string to parse.
     *
     * Returns:
     *   - int: The memory limit in bytes.
     */
    private static function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }
}
