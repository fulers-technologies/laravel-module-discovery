<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Constants;

/**
 * ComposerEventConstants defines all Composer event-related constant values.
 * This class centralizes Composer event identifiers and script configurations
 * to prevent hardcoding and ensure consistent event handling across the system.
 *
 * The constants include Composer script events, hook configurations, and
 * event-related settings used for automatic module discovery integration.
 */
final class ComposerEventConstants
{
    /**
     * Composer event triggered after package installation.
     * This event fires when 'composer install' completes successfully
     * and is used to trigger automatic module discovery operations.
     */
    public const POST_INSTALL_CMD = 'post-install-cmd';

    /**
     * Composer event triggered after package updates.
     * This event fires when 'composer update' completes successfully
     * and ensures module discovery runs after dependency changes.
     */
    public const POST_UPDATE_CMD = 'post-update-cmd';

    /**
     * Composer event triggered after autoload dump operations.
     * This event fires when 'composer dump-autoload' completes
     * and provides an opportunity to register newly discovered namespaces.
     */
    public const POST_AUTOLOAD_DUMP = 'post-autoload-dump';

    /**
     * Composer event triggered before package installation.
     * This event fires before 'composer install' begins
     * and can be used for pre-installation setup operations.
     */
    public const PRE_INSTALL_CMD = 'pre-install-cmd';

    /**
     * Composer event triggered before package updates.
     * This event fires before 'composer update' begins
     * and can be used for pre-update preparation operations.
     */
    public const PRE_UPDATE_CMD = 'pre-update-cmd';

    /**
     * Composer event triggered before autoload dump operations.
     * This event fires before 'composer dump-autoload' begins
     * and can be used for pre-dump preparation operations.
     */
    public const PRE_AUTOLOAD_DUMP = 'pre-autoload-dump';

    /**
     * Script class name for module discovery operations.
     * The fully qualified class name that handles module discovery
     * during Composer script execution.
     */
    public const DISCOVERY_SCRIPT_CLASS = 'LaravelModuleDiscovery\\ComposerHook\\Scripts\\ComposerScripts';

    /**
     * Script class name for hook installation operations.
     * The fully qualified class name that handles Composer hook
     * installation and configuration.
     */
    public const INSTALLER_SCRIPT_CLASS = 'LaravelModuleDiscovery\\ComposerHook\\Scripts\\ComposerHookInstaller';

    /**
     * Default timeout for script execution in seconds.
     * Maximum time allowed for module discovery operations
     * during Composer script execution.
     */
    public const DEFAULT_SCRIPT_TIMEOUT = 60;

    /**
     * Default memory limit for script execution.
     * Memory limit setting for module discovery operations
     * to ensure adequate resources during execution.
     */
    public const DEFAULT_MEMORY_LIMIT = '256M';

    /**
     * Priority level for module discovery scripts.
     * Determines the execution order when multiple scripts
     * are registered for the same Composer event.
     */
    public const SCRIPT_PRIORITY = 100;

    /**
     * Configuration key for enabling automatic discovery.
     * Configuration setting that controls whether module discovery
     * should run automatically during Composer operations.
     */
    public const AUTO_DISCOVERY_CONFIG_KEY = 'auto_discovery_enabled';

    /**
     * Configuration key for script timeout settings.
     * Configuration setting that controls the timeout value
     * for module discovery script execution.
     */
    public const SCRIPT_TIMEOUT_CONFIG_KEY = 'script_timeout';

    /**
     * Configuration key for memory limit settings.
     * Configuration setting that controls the memory limit
     * for module discovery script execution.
     */
    public const MEMORY_LIMIT_CONFIG_KEY = 'script_memory_limit';

    /**
     * Environment variable for disabling automatic discovery.
     * Environment variable that can be set to disable automatic
     * module discovery during Composer operations.
     */
    public const DISABLE_AUTO_DISCOVERY_ENV = 'LARAVEL_MODULE_DISCOVERY_DISABLE';

    /**
     * Environment variable for enabling verbose output.
     * Environment variable that can be set to enable verbose
     * output during automatic module discovery operations.
     */
    public const VERBOSE_OUTPUT_ENV = 'LARAVEL_MODULE_DISCOVERY_VERBOSE';

    /**
     * Environment variable for debug mode.
     * Environment variable that can be set to enable debug mode
     * during automatic module discovery operations.
     */
    public const DEBUG_MODE_ENV = 'LARAVEL_MODULE_DISCOVERY_DEBUG';

    /**
     * Default script hooks configuration.
     * Array of Composer events mapped to their corresponding
     * script method calls for automatic module discovery.
     *
     * @var array<string, string>
     */
    public const DEFAULT_SCRIPT_HOOKS = [
        self::POST_UPDATE_CMD => self::DISCOVERY_SCRIPT_CLASS . '::postUpdate',
        self::POST_INSTALL_CMD => self::DISCOVERY_SCRIPT_CLASS . '::postInstall',
        self::POST_AUTOLOAD_DUMP => self::DISCOVERY_SCRIPT_CLASS . '::postAutoloadDump',
    ];

    /**
     * Installer script hooks configuration.
     * Array of Composer events mapped to installer script methods
     * for automatic hook installation and configuration.
     *
     * @var array<string, string>
     */
    public const INSTALLER_SCRIPT_HOOKS = [
        self::POST_INSTALL_CMD => self::INSTALLER_SCRIPT_CLASS . '::install',
        'pre-package-uninstall' => self::INSTALLER_SCRIPT_CLASS . '::uninstall',
    ];

    /**
     * Script execution environment configuration.
     * Default environment settings for script execution including
     * timeout, memory limit, and error reporting settings.
     *
     * @var array<string, mixed>
     */
    public const SCRIPT_ENVIRONMENT_CONFIG = [
        'log_errors' => true,
        'display_errors' => false,
        'error_reporting' => E_ERROR | E_WARNING,
        'timeout' => self::DEFAULT_SCRIPT_TIMEOUT,
        'memory_limit' => self::DEFAULT_MEMORY_LIMIT,
    ];
}
