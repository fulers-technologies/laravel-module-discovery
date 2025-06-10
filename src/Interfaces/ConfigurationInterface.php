<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * ConfigurationInterface defines the contract for configuration management operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on configuration access and management without mixing other concerns.
 *
 * The interface provides methods to retrieve configuration values, validate settings,
 * and manage configuration state throughout the module discovery system.
 */
interface ConfigurationInterface
{
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
    public function get(string $key, mixed $default = null): mixed;

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
    public function has(string $key): bool;

    /**
     * Retrieves all configuration values as an array.
     * Returns the complete configuration array for inspection
     * or bulk processing operations.
     *
     * Returns:
     *   - array<string, mixed>: The complete configuration array.
     */
    public function all(): array;

    /**
     * Validates the current configuration settings.
     * Checks all configuration values for validity and consistency
     * to ensure proper system operation.
     *
     * Returns:
     *   - bool: True if configuration is valid, false otherwise.
     */
    public function validate(): bool;

    /**
     * Gets the default modules directory path.
     * Returns the configured default directory where modules should be scanned.
     *
     * Returns:
     *   - string: The default modules directory path.
     */
    public function getDefaultModulesDirectory(): string;

    /**
     * Gets the supported file extensions for discovery.
     * Returns an array of file extensions that should be processed.
     *
     * Returns:
     *   - array<string>: Array of supported file extensions.
     */
    public function getSupportedExtensions(): array;

    /**
     * Gets the maximum scan depth for directory traversal.
     * Returns the maximum depth for recursive directory scanning.
     *
     * Returns:
     *   - int: The maximum scan depth.
     */
    public function getMaxScanDepth(): int;

    /**
     * Gets the maximum number of tokens to examine for namespace detection.
     * Returns the limit for token parsing to improve performance.
     *
     * Returns:
     *   - int: The maximum number of tokens to examine.
     */
    public function getMaxTokensToExamine(): int;

    /**
     * Gets the discovery operation timeout in seconds.
     * Returns the maximum time allowed for discovery operations.
     *
     * Returns:
     *   - int: The timeout in seconds.
     */
    public function getTimeoutSeconds(): int;

    /**
     * Checks if caching is enabled for namespace extraction.
     * Returns whether caching should be used to improve performance.
     *
     * Returns:
     *   - bool: True if caching is enabled, false otherwise.
     */
    public function isCachingEnabled(): bool;

    /**
     * Checks if hidden files should be skipped during scanning.
     * Returns whether hidden files and directories should be ignored.
     *
     * Returns:
     *   - bool: True if hidden files should be skipped, false otherwise.
     */
    public function shouldSkipHiddenFiles(): bool;

    /**
     * Gets the excluded namespace prefixes.
     * Returns an array of namespace prefixes that should be excluded from discovery.
     *
     * Returns:
     *   - array<string>: Array of excluded namespace prefixes.
     */
    public function getExcludedNamespacePrefixes(): array;

    /**
     * Gets the excluded directory names.
     * Returns an array of directory names that should be skipped during scanning.
     *
     * Returns:
     *   - array<string>: Array of excluded directory names.
     */
    public function getExcludedDirectories(): array;

    /**
     * Checks if strict PSR-4 validation is enabled.
     * Returns whether namespace validation should follow strict PSR-4 standards.
     *
     * Returns:
     *   - bool: True if strict validation is enabled, false otherwise.
     */
    public function isStrictPsr4ValidationEnabled(): bool;

    /**
     * Gets the minimum namespace length requirement.
     * Returns the minimum length required for namespace registration.
     *
     * Returns:
     *   - int: The minimum namespace length.
     */
    public function getMinNamespaceLength(): int;

    /**
     * Gets the maximum namespace length allowed.
     * Returns the maximum length allowed for namespace registration.
     *
     * Returns:
     *   - int: The maximum namespace length.
     */
    public function getMaxNamespaceLength(): int;

    /**
     * Checks if automatic namespace registration is enabled.
     * Returns whether discovered namespaces should be automatically registered.
     *
     * Returns:
     *   - bool: True if auto-registration is enabled, false otherwise.
     */
    public function isAutoRegisterNamespacesEnabled(): bool;

    /**
     * Checks if force re-registration is enabled.
     * Returns whether existing namespace mappings should be overwritten.
     *
     * Returns:
     *   - bool: True if force re-registration is enabled, false otherwise.
     */
    public function isForceReregistrationEnabled(): bool;

    /**
     * Checks if batch registration is enabled.
     * Returns whether multiple namespaces should be registered in batches.
     *
     * Returns:
     *   - bool: True if batch registration is enabled, false otherwise.
     */
    public function isBatchRegistrationEnabled(): bool;

    /**
     * Checks if auto-apply registrations is enabled.
     * Returns whether registrations should be automatically applied after discovery.
     *
     * Returns:
     *   - bool: True if auto-apply is enabled, false otherwise.
     */
    public function isAutoApplyRegistrationsEnabled(): bool;

    /**
     * Gets the suggested directories for module discovery.
     * Returns an array of directory suggestions when the default is not found.
     *
     * Returns:
     *   - array<string>: Array of suggested directory paths.
     */
    public function getSuggestedDirectories(): array;

    /**
     * Checks if detailed logging is enabled.
     * Returns whether detailed logging should be performed during discovery.
     *
     * Returns:
     *   - bool: True if detailed logging is enabled, false otherwise.
     */
    public function isDetailedLoggingEnabled(): bool;

    /**
     * Gets the log level for discovery operations.
     * Returns the configured log level for discovery logging.
     *
     * Returns:
     *   - string: The log level (e.g., 'info', 'debug', 'error').
     */
    public function getLogLevel(): string;

    /**
     * Gets the log channel for discovery operations.
     * Returns the configured log channel for discovery logging.
     *
     * Returns:
     *   - string: The log channel name.
     */
    public function getLogChannel(): string;

    /**
     * Checks if progress indicators should be shown.
     * Returns whether progress bars and status updates should be displayed.
     *
     * Returns:
     *   - bool: True if progress indicators should be shown, false otherwise.
     */
    public function shouldShowProgressIndicators(): bool;

    /**
     * Checks if discovery should continue on errors.
     * Returns whether individual file errors should stop the entire process.
     *
     * Returns:
     *   - bool: True if discovery should continue on errors, false otherwise.
     */
    public function shouldContinueOnErrors(): bool;

    /**
     * Gets the maximum number of errors before stopping discovery.
     * Returns the error threshold for stopping discovery operations.
     *
     * Returns:
     *   - int: The maximum number of errors allowed.
     */
    public function getMaxErrorsBeforeStop(): int;

    /**
     * Checks if debug mode is enabled.
     * Returns whether debug mode is active for detailed error reporting.
     *
     * Returns:
     *   - bool: True if debug mode is enabled, false otherwise.
     */
    public function isDebugModeEnabled(): bool;

    /**
     * Checks if dry run mode is enabled.
     * Returns whether discovery should run without actual registration.
     *
     * Returns:
     *   - bool: True if dry run mode is enabled, false otherwise.
     */
    public function isDryRunModeEnabled(): bool;

}
