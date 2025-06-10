<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Constants\ComposerEventConstants;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;

/**
 * ComposerEventService handles Composer event management and configuration.
 * This service provides methods to manage Composer script hooks, validate
 * event configurations, and handle event-driven module discovery operations.
 *
 * The service centralizes all Composer event-related functionality and
 * provides a clean interface for event management operations.
 */
class ComposerEventService
{
    /**
     * Creates a new ComposerEventService instance.
     * Initializes the service with configuration management for
     * controlling event behavior and script execution settings.
     *
     * Parameters:
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly ConfigurationInterface $configuration
    ) {
    }

    /**
     * Creates a new ComposerEventService instance using static factory method.
     * Provides a convenient way to instantiate the service without using the new keyword.
     *
     * Parameters:
     *   - ConfigurationInterface|null $configuration: Optional custom configuration service.
     *
     * Returns:
     *   - static: A new ComposerEventService instance.
     */
    public static function make(?ConfigurationInterface $configuration = null): static
    {
        return new static(
            $configuration ?? ConfigurationService::make()
        );
    }

    /**
     * Checks if automatic discovery is enabled.
     * Determines whether module discovery should run automatically
     * during Composer operations based on configuration and environment.
     *
     * Returns:
     *   - bool: True if automatic discovery is enabled, false otherwise.
     */
    public function isAutoDiscoveryEnabled(): bool
    {
        // Check environment variable first
        if (getenv(ComposerEventConstants::DISABLE_AUTO_DISCOVERY_ENV) === 'true') {
            return false;
        }

        // Check configuration setting
        return $this->configuration->get(
            ComposerEventConstants::AUTO_DISCOVERY_CONFIG_KEY,
            true
        );
    }

    /**
     * Checks if verbose output is enabled for scripts.
     * Determines whether verbose output should be displayed
     * during automatic module discovery operations.
     *
     * Returns:
     *   - bool: True if verbose output is enabled, false otherwise.
     */
    public function isVerboseOutputEnabled(): bool
    {
        // Check environment variable first
        if (getenv(ComposerEventConstants::VERBOSE_OUTPUT_ENV) === 'true') {
            return true;
        }

        // Check configuration setting
        return $this->configuration->get('logging.show_progress_indicators', true);
    }

    /**
     * Checks if debug mode is enabled for scripts.
     * Determines whether debug mode should be active
     * during automatic module discovery operations.
     *
     * Returns:
     *   - bool: True if debug mode is enabled, false otherwise.
     */
    public function isDebugModeEnabled(): bool
    {
        // Check environment variable first
        if (getenv(ComposerEventConstants::DEBUG_MODE_ENV) === 'true') {
            return true;
        }

        // Check configuration setting
        return $this->configuration->isDebugModeEnabled();
    }

    /**
     * Gets the script execution timeout.
     * Returns the maximum time allowed for module discovery
     * script execution during Composer operations.
     *
     * Returns:
     *   - int: The timeout in seconds.
     */
    public function getScriptTimeout(): int
    {
        return (int) $this->configuration->get(
            ComposerEventConstants::SCRIPT_TIMEOUT_CONFIG_KEY,
            ComposerEventConstants::DEFAULT_SCRIPT_TIMEOUT
        );
    }

    /**
     * Gets the script memory limit.
     * Returns the memory limit setting for module discovery
     * script execution during Composer operations.
     *
     * Returns:
     *   - string: The memory limit (e.g., "256M").
     */
    public function getScriptMemoryLimit(): string
    {
        return (string) $this->configuration->get(
            ComposerEventConstants::MEMORY_LIMIT_CONFIG_KEY,
            ComposerEventConstants::DEFAULT_MEMORY_LIMIT
        );
    }

    /**
     * Gets the default script hooks configuration.
     * Returns the mapping of Composer events to script methods
     * for automatic module discovery operations.
     *
     * Returns:
     *   - array<string, string>: Array of event names to script methods.
     */
    public function getDefaultScriptHooks(): array
    {
        return ComposerEventConstants::DEFAULT_SCRIPT_HOOKS;
    }

    /**
     * Gets the installer script hooks configuration.
     * Returns the mapping of Composer events to installer script methods
     * for automatic hook installation and configuration.
     *
     * Returns:
     *   - array<string, string>: Array of event names to installer methods.
     */
    public function getInstallerScriptHooks(): array
    {
        return ComposerEventConstants::INSTALLER_SCRIPT_HOOKS;
    }

    /**
     * Gets the script execution environment configuration.
     * Returns the environment settings for script execution including
     * timeout, memory limit, and error reporting settings.
     *
     * Returns:
     *   - array<string, mixed>: Array of environment configuration settings.
     */
    public function getScriptEnvironmentConfig(): array
    {
        $config = ComposerEventConstants::SCRIPT_ENVIRONMENT_CONFIG;

        // Override with configuration values
        $config['timeout'] = $this->getScriptTimeout();
        $config['memory_limit'] = $this->getScriptMemoryLimit();

        // Adjust error reporting based on debug mode
        if ($this->isDebugModeEnabled()) {
            $config['error_reporting'] = E_ALL;
            $config['display_errors'] = true;
        }

        return $config;
    }

    /**
     * Validates the Composer event configuration.
     * Checks that all required event configurations are valid
     * and properly formatted for script execution.
     *
     * Returns:
     *   - bool: True if configuration is valid, false otherwise.
     */
    public function validateEventConfiguration(): bool
    {
        try {
            // Validate timeout setting
            $timeout = $this->getScriptTimeout();
            if ($timeout < 1 || $timeout > 3600) {
                return false;
            }

            // Validate memory limit setting
            $memoryLimit = $this->getScriptMemoryLimit();
            if (!preg_match('/^\d+[KMG]?$/i', $memoryLimit)) {
                return false;
            }

            // Validate script hooks
            $hooks = $this->getDefaultScriptHooks();
            foreach ($hooks as $event => $method) {
                if (!is_string($event) || !is_string($method)) {
                    return false;
                }

                if (!str_contains($method, '::')) {
                    return false;
                }
            }

            return true;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Formats event output message.
     * Creates a formatted message for display during Composer
     * event execution with appropriate styling and context.
     *
     * Parameters:
     *   - string $message: The message to format.
     *   - string $type: The message type (info, warning, error).
     *
     * Returns:
     *   - string: The formatted message.
     */
    public function formatEventMessage(string $message, string $type = 'info'): string
    {
        $prefix = match ($type) {
            'info' => '🔍 <info>Laravel Module Discovery:</info>',
            'warning' => '⚠️  <comment>Laravel Module Discovery:</comment>',
            'error' => '❌ <error>Laravel Module Discovery:</error>',
            'success' => '✅ <info>Laravel Module Discovery:</info>',
            default => 'ℹ️  <comment>Laravel Module Discovery:</comment>',
        };

        return "{$prefix} {$message}";
    }

    /**
     * Gets the event execution context.
     * Returns contextual information about the current event
     * execution environment and settings.
     *
     * Returns:
     *   - array<string, mixed>: Array of execution context information.
     */
    public function getEventExecutionContext(): array
    {
        return [
            'auto_discovery_enabled' => $this->isAutoDiscoveryEnabled(),
            'verbose_output_enabled' => $this->isVerboseOutputEnabled(),
            'debug_mode_enabled' => $this->isDebugModeEnabled(),
            'script_timeout' => $this->getScriptTimeout(),
            'script_memory_limit' => $this->getScriptMemoryLimit(),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'working_directory' => getcwd(),
        ];
    }

    /**
     * Logs event execution information.
     * Records information about event execution for debugging
     * and monitoring purposes.
     *
     * Parameters:
     *   - string $event: The event name.
     *   - array<string, mixed> $context: Additional context information.
     */
    public function logEventExecution(string $event, array $context = []): void
    {
        if (!$this->configuration->isDetailedLoggingEnabled()) {
            return;
        }

        $logData = [
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context,
            'execution_context' => $this->getEventExecutionContext(),
        ];

        // Log to configured channel
        $logChannel = $this->configuration->getLogChannel();

        if (function_exists('logger')) {
            logger()->channel($logChannel)->info('Composer event executed', $logData);
        } else {
            error_log('Laravel Module Discovery: ' . json_encode($logData));
        }
    }
}
