<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;

/**
 * ConfigurationService implements configuration management for the module discovery system.
 * This service handles loading, accessing, and validating configuration values
 * from the module-discovery.php configuration file and environment variables.
 *
 * The service provides a centralized way to access all package configuration
 * and ensures consistent configuration handling throughout the system.
 */
class ConfigurationService implements ConfigurationInterface
{
    /**
     * The loaded configuration array.
     * Contains all configuration values loaded from the configuration file
     * and processed for use throughout the system.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * The configuration file name.
     * Specifies the name of the configuration file to load
     * from Laravel's config directory.
     */
    private const CONFIG_FILE = 'module-discovery';

    /**
     * Creates a new ConfigurationService instance.
     * Initializes the service and loads configuration values
     * from the Laravel configuration system.
     *
     * Parameters:
     *   - array<string, mixed>|null $config: Optional configuration array for testing.
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfiguration();
    }

    /**
     * Creates a new ConfigurationService instance using static factory method.
     * Provides a convenient way to instantiate the service without using the new keyword.
     *
     * Parameters:
     *   - array<string, mixed>|null $config: Optional configuration array for testing.
     *
     * Returns:
     *   - static: A new ConfigurationService instance.
     */
    public static function make(?array $config = null): static
    {
        return new static($config);
    }

    /**
     * Retrieves a configuration value by key with optional default.
     * Returns the configuration value for the specified key path,
     * or the default value if the key is not found.
     *
     * Parameters:
     *   - string $key: The configuration key path (supports dot notation).
     *   - mixed $default: The default value to return if key is not found.
     *
     * Returns:
     *   - mixed: The configuration value or default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    /**
     * Checks if a configuration key exists.
     * Determines whether the specified configuration key
     * is defined in the configuration system.
     *
     * Parameters:
     *   - string $key: The configuration key path to check.
     *
     * Returns:
     *   - bool: True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return $this->getNestedValue($this->config, $key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    /**
     * Retrieves all configuration values as an array.
     * Returns the complete configuration array for inspection
     * or bulk processing operations.
     *
     * Returns:
     *   - array<string, mixed>: The complete configuration array.
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Validates the current configuration settings.
     * Checks all configuration values for validity and consistency
     * to ensure proper system operation.
     *
     * Returns:
     *   - bool: True if configuration is valid, false otherwise.
     */
    public function validate(): bool
    {
        try {
            // Validate required configuration keys
            $requiredKeys = [
                'default_modules_directory',
                'supported_extensions',
                'discovery.max_scan_depth',
                'discovery.max_tokens_to_examine',
                'discovery.timeout_seconds',
            ];

            foreach ($requiredKeys as $key) {
                if (!$this->has($key)) {
                    return false;
                }
            }

            // Validate data types and ranges
            if (!is_string($this->get('default_modules_directory'))) {
                return false;
            }

            if (!is_array($this->get('supported_extensions'))) {
                return false;
            }

            $maxDepth = $this->getMaxScanDepth();
            if ($maxDepth < 1 || $maxDepth > 50) {
                return false;
            }

            $maxTokens = $this->getMaxTokensToExamine();
            if ($maxTokens < 10 || $maxTokens > 10000) {
                return false;
            }

            $timeout = $this->getTimeoutSeconds();
            if ($timeout < 1 || $timeout > 3600) {
                return false;
            }

            return true;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Gets the default modules directory path.
     * Returns the configured default directory where modules should be scanned.
     *
     * Returns:
     *   - string: The default modules directory path.
     */
    public function getDefaultModulesDirectory(): string
    {
        return (string) $this->get('default_modules_directory', 'app/Modules');
    }

    /**
     * Gets the supported file extensions for discovery.
     * Returns an array of file extensions that should be processed.
     *
     * Returns:
     *   - array<string>: Array of supported file extensions.
     */
    public function getSupportedExtensions(): array
    {
        $extensions = $this->get('supported_extensions', ['php']);
        return is_array($extensions) ? $extensions : ['php'];
    }

    /**
     * Gets the maximum scan depth for directory traversal.
     * Returns the maximum depth for recursive directory scanning.
     *
     * Returns:
     *   - int: The maximum scan depth.
     */
    public function getMaxScanDepth(): int
    {
        return (int) $this->get('discovery.max_scan_depth', 10);
    }

    /**
     * Gets the maximum number of tokens to examine for namespace detection.
     * Returns the limit for token parsing to improve performance.
     *
     * Returns:
     *   - int: The maximum number of tokens to examine.
     */
    public function getMaxTokensToExamine(): int
    {
        return (int) $this->get('discovery.max_tokens_to_examine', 100);
    }

    /**
     * Gets the discovery operation timeout in seconds.
     * Returns the maximum time allowed for discovery operations.
     *
     * Returns:
     *   - int: The timeout in seconds.
     */
    public function getTimeoutSeconds(): int
    {
        return (int) $this->get('discovery.timeout_seconds', 300);
    }

    /**
     * Checks if caching is enabled for namespace extraction.
     * Returns whether caching should be used to improve performance.
     *
     * Returns:
     *   - bool: True if caching is enabled, false otherwise.
     */
    public function isCachingEnabled(): bool
    {
        return (bool) $this->get('discovery.enable_caching', true);
    }

    /**
     * Checks if hidden files should be skipped during scanning.
     * Returns whether hidden files and directories should be ignored.
     *
     * Returns:
     *   - bool: True if hidden files should be skipped, false otherwise.
     */
    public function shouldSkipHiddenFiles(): bool
    {
        return (bool) $this->get('discovery.skip_hidden_files', true);
    }

    /**
     * Gets the excluded namespace prefixes.
     * Returns an array of namespace prefixes that should be excluded from discovery.
     *
     * Returns:
     *   - array<string>: Array of excluded namespace prefixes.
     */
    public function getExcludedNamespacePrefixes(): array
    {
        $prefixes = $this->get('validation.excluded_namespace_prefixes', []);
        return is_array($prefixes) ? $prefixes : [];
    }

    /**
     * Gets the excluded directory names.
     * Returns an array of directory names that should be skipped during scanning.
     *
     * Returns:
     *   - array<string>: Array of excluded directory names.
     */
    public function getExcludedDirectories(): array
    {
        $directories = $this->get('validation.excluded_directories', []);
        return is_array($directories) ? $directories : [];
    }

    /**
     * Checks if strict PSR-4 validation is enabled.
     * Returns whether namespace validation should follow strict PSR-4 standards.
     *
     * Returns:
     *   - bool: True if strict validation is enabled, false otherwise.
     */
    public function isStrictPsr4ValidationEnabled(): bool
    {
        return (bool) $this->get('validation.strict_psr4_validation', true);
    }

    /**
     * Gets the minimum namespace length requirement.
     * Returns the minimum length required for namespace registration.
     *
     * Returns:
     *   - int: The minimum namespace length.
     */
    public function getMinNamespaceLength(): int
    {
        return (int) $this->get('validation.min_namespace_length', 3);
    }

    /**
     * Gets the maximum namespace length allowed.
     * Returns the maximum length allowed for namespace registration.
     *
     * Returns:
     *   - int: The maximum namespace length.
     */
    public function getMaxNamespaceLength(): int
    {
        return (int) $this->get('validation.max_namespace_length', 255);
    }

    /**
     * Checks if automatic namespace registration is enabled.
     * Returns whether discovered namespaces should be automatically registered.
     *
     * Returns:
     *   - bool: True if auto-registration is enabled, false otherwise.
     */
    public function isAutoRegisterNamespacesEnabled(): bool
    {
        return (bool) $this->get('composer.auto_register_namespaces', true);
    }

    /**
     * Checks if force re-registration is enabled.
     * Returns whether existing namespace mappings should be overwritten.
     *
     * Returns:
     *   - bool: True if force re-registration is enabled, false otherwise.
     */
    public function isForceReregistrationEnabled(): bool
    {
        return (bool) $this->get('composer.force_reregistration', false);
    }

    /**
     * Checks if batch registration is enabled.
     * Returns whether multiple namespaces should be registered in batches.
     *
     * Returns:
     *   - bool: True if batch registration is enabled, false otherwise.
     */
    public function isBatchRegistrationEnabled(): bool
    {
        return (bool) $this->get('composer.enable_batch_registration', true);
    }

    /**
     * Checks if auto-apply registrations is enabled.
     * Returns whether registrations should be automatically applied after discovery.
     *
     * Returns:
     *   - bool: True if auto-apply is enabled, false otherwise.
     */
    public function isAutoApplyRegistrationsEnabled(): bool
    {
        return (bool) $this->get('composer.auto_apply_registrations', true);
    }

    /**
     * Gets the suggested directories for module discovery.
     * Returns an array of directory suggestions when the default is not found.
     *
     * Returns:
     *   - array<string>: Array of suggested directory paths.
     */
    public function getSuggestedDirectories(): array
    {
        $directories = $this->get('suggested_directories', [
            'app/Modules',
            'modules',
            'src/Modules',
            'packages',
        ]);
        return is_array($directories) ? $directories : [];
    }

    /**
     * Checks if detailed logging is enabled.
     * Returns whether detailed logging should be performed during discovery.
     *
     * Returns:
     *   - bool: True if detailed logging is enabled, false otherwise.
     */
    public function isDetailedLoggingEnabled(): bool
    {
        return (bool) $this->get('logging.enable_detailed_logging', false);
    }

    /**
     * Gets the log level for discovery operations.
     * Returns the configured log level for discovery logging.
     *
     * Returns:
     *   - string: The log level (e.g., 'info', 'debug', 'error').
     */
    public function getLogLevel(): string
    {
        return (string) $this->get('logging.log_level', 'info');
    }

    /**
     * Gets the log channel for discovery operations.
     * Returns the configured log channel for discovery logging.
     *
     * Returns:
     *   - string: The log channel name.
     */
    public function getLogChannel(): string
    {
        return (string) $this->get('logging.log_channel', 'single');
    }

    /**
     * Checks if progress indicators should be shown.
     * Returns whether progress bars and status updates should be displayed.
     *
     * Returns:
     *   - bool: True if progress indicators should be shown, false otherwise.
     */
    public function shouldShowProgressIndicators(): bool
    {
        return (bool) $this->get('logging.show_progress_indicators', true);
    }

    /**
     * Checks if discovery should continue on errors.
     * Returns whether individual file errors should stop the entire process.
     *
     * Returns:
     *   - bool: True if discovery should continue on errors, false otherwise.
     */
    public function shouldContinueOnErrors(): bool
    {
        return (bool) $this->get('error_handling.continue_on_errors', true);
    }

    /**
     * Gets the maximum number of errors before stopping discovery.
     * Returns the error threshold for stopping discovery operations.
     *
     * Returns:
     *   - int: The maximum number of errors allowed.
     */
    public function getMaxErrorsBeforeStop(): int
    {
        return (int) $this->get('error_handling.max_errors_before_stop', 10);
    }

    /**
     * Checks if debug mode is enabled.
     * Returns whether debug mode is active for detailed error reporting.
     *
     * Returns:
     *   - bool: True if debug mode is enabled, false otherwise.
     */
    public function isDebugModeEnabled(): bool
    {
        return (bool) $this->get('development.debug_mode', false);
    }

    /**
     * Checks if dry run mode is enabled.
     * Returns whether discovery should run without actual registration.
     *
     * Returns:
     *   - bool: True if dry run mode is enabled, false otherwise.
     */
    public function isDryRunModeEnabled(): bool
    {
        return (bool) $this->get('development.dry_run_mode', false);
    }

    /**
     * Loads configuration from the Laravel configuration system.
     * Retrieves the module-discovery configuration array from Laravel's config.
     *
     * Returns:
     *   - array<string, mixed>: The loaded configuration array.
     */
    private function loadConfiguration(): array
    {
        // Try to load from Laravel config system
        if (function_exists('config')) {
            $config = config(self::CONFIG_FILE);
            if (is_array($config)) {
                return $config;
            }
        }

        // Fallback to loading directly from file
        $configPath = $this->getConfigFilePath();
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (is_array($config)) {
                return $config;
            }
        }

        // Return default configuration if file not found
        return $this->getDefaultConfiguration();
    }

    /**
     * Gets the path to the configuration file.
     * Returns the full path to the module-discovery.php configuration file.
     *
     * Returns:
     *   - string: The configuration file path.
     */
    private function getConfigFilePath(): string
    {
        // Try Laravel config path first
        if (function_exists('config_path')) {
            return config_path(self::CONFIG_FILE . '.php');
        }

        // Fallback to package config directory
        return __DIR__ . '/../../config/' . self::CONFIG_FILE . '.php';
    }

    /**
     * Gets the default configuration array.
     * Returns a minimal default configuration when the config file is not available.
     *
     * Returns:
     *   - array<string, mixed>: The default configuration array.
     */
    private function getDefaultConfiguration(): array
    {
        return [
            'default_modules_directory' => 'app/Modules',
            'supported_extensions' => ['php'],
            'discovery' => [
                'max_scan_depth' => 10,
                'max_tokens_to_examine' => 100,
                'timeout_seconds' => 300,
                'enable_caching' => true,
                'skip_hidden_files' => true,
            ],
            'validation' => [
                'strict_psr4_validation' => true,
                'min_namespace_length' => 3,
                'max_namespace_length' => 255,
                'excluded_namespace_prefixes' => [],
                'excluded_directories' => ['vendor', 'node_modules', 'storage'],
            ],
            'composer' => [
                'auto_register_namespaces' => true,
                'force_reregistration' => false,
                'enable_batch_registration' => true,
                'auto_apply_registrations' => true,
            ],
            'error_handling' => [
                'continue_on_errors' => true,
                'max_errors_before_stop' => 10,
                'enable_retry_logic' => false,
                'max_retry_attempts' => 3,
                'retry_delay_ms' => 100,
            ],
            'logging' => [
                'enable_detailed_logging' => false,
                'log_level' => 'info',
                'log_channel' => 'single',
                'show_progress_indicators' => true,
                'default_verbosity' => 'normal',
            ],
            'development' => [
                'debug_mode' => false,
                'dry_run_mode' => false,
                'enable_profiling' => false,
                'save_results_to_file' => false,
                'results_file_path' => storage_path('logs/module-discovery-results.json'),
            ],
            'suggested_directories' => [
                'app/Modules',
                'modules',
                'src/Modules',
                'packages',
            ],
        ];
    }

    /**
     * Retrieves a nested value from an array using dot notation.
     * Supports accessing nested array values using dot-separated keys.
     *
     * Parameters:
     *   - array<string, mixed> $array: The array to search in.
     *   - string $key: The dot-notation key path.
     *   - mixed $default: The default value if key is not found.
     *
     * Returns:
     *   - mixed: The found value or default value.
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
