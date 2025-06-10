<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Constants;

/**
 * EventConstants defines all Composer event-related constant values used
 * throughout the module discovery system. This class centralizes event
 * identifiers and configuration to prevent hardcoding and ensure consistent
 * event handling across the Composer hook integration.
 *
 * The constants include Composer script events, command identifiers, and
 * event-related configuration used for automatic class discovery triggering.
 */
final class EventConstants
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
     * Artisan command signature for module discovery.
     * The command signature used to trigger the module discovery process
     * through Laravel's Artisan command-line interface.
     */
    public const MODULE_DISCOVER_COMMAND = 'module:discover';

    /**
     * Event priority level for module discovery operations.
     * Determines the execution order when multiple event handlers
     * are registered for the same Composer event.
     */
    public const DISCOVERY_EVENT_PRIORITY = 100;

    /**
     * Timeout value for discovery operations in seconds.
     * Maximum time allowed for module discovery operations
     * to prevent hanging during large directory scans.
     */
    public const DISCOVERY_TIMEOUT_SECONDS = 300;

    /**
     * Event status indicating successful completion.
     * Status code returned when module discovery operations
     * complete successfully without errors.
     */
    public const SUCCESS_STATUS = 'success';

    /**
     * Event status indicating operation failure.
     * Status code returned when module discovery operations
     * encounter errors or fail to complete.
     */
    public const FAILURE_STATUS = 'failure';
}