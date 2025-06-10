<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Enums;

/**
 * DiscoveryStatusEnum defines the possible status values for module discovery operations.
 * This enumeration provides type-safe status indicators that represent different
 * states of the class discovery process, from initialization through completion.
 *
 * The enum ensures consistent status reporting across the discovery system
 * and prevents the use of magic strings for status representation.
 */
enum DiscoveryStatusEnum: string
{
    /**
     * Status indicating the discovery process has been initialized.
     * This status is set when the discovery operation begins
     * but before any actual scanning or processing occurs.
     */
    case INITIALIZED = 'initialized';

    /**
     * Status indicating the discovery process is currently running.
     * This status is active during directory scanning, namespace extraction,
     * and autoloader registration operations.
     */
    case IN_PROGRESS = 'in_progress';

    /**
     * Status indicating the discovery process completed successfully.
     * This status is set when all operations complete without errors
     * and all discovered namespaces are registered with the autoloader.
     */
    case COMPLETED = 'completed';

    /**
     * Status indicating the discovery process encountered an error.
     * This status is set when any critical error occurs during
     * the discovery process that prevents successful completion.
     */
    case FAILED = 'failed';

    /**
     * Status indicating the discovery process was skipped.
     * This status is set when the discovery operation is bypassed
     * due to configuration settings or environmental conditions.
     */
    case SKIPPED = 'skipped';

    /**
     * Status indicating the discovery process was cancelled.
     * This status is set when the operation is interrupted
     * or terminated before natural completion.
     */
    case CANCELLED = 'cancelled';

    /**
     * Gets the human-readable description for the status.
     * Provides user-friendly text that explains the current
     * state of the discovery operation for logging and display.
     *
     * Returns:
     *   - string: A descriptive message explaining the status.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::INITIALIZED => 'Discovery process has been initialized and is ready to start',
            self::IN_PROGRESS => 'Discovery process is currently scanning directories and extracting namespaces',
            self::COMPLETED => 'Discovery process completed successfully and all namespaces are registered',
            self::FAILED => 'Discovery process encountered an error and could not complete',
            self::SKIPPED => 'Discovery process was skipped due to configuration or environmental conditions',
            self::CANCELLED => 'Discovery process was cancelled before completion',
        };
    }

    /**
     * Determines if the status represents a terminal state.
     * Terminal states indicate that the discovery process has
     * finished and will not continue further processing.
     *
     * Returns:
     *   - bool: True if the status is terminal, false if processing can continue.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::SKIPPED, self::CANCELLED => true,
            self::INITIALIZED, self::IN_PROGRESS => false,
        };
    }
}