<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * ClassDiscoveryInterface defines the contract for class discovery functionality.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * solely on class discovery operations without mixing other responsibilities.
 *
 * The interface provides methods to discover classes within specified directories,
 * handle discovery results, and manage the discovery process lifecycle.
 */
interface ClassDiscoveryInterface
{
    /**
     * Discovers classes within the specified directory path.
     * Scans through the directory structure to identify PHP classes
     * and extract their namespace information for autoloading registration.
     *
     * Parameters:
     *   - string $directoryPath: The absolute path to the directory to scan for classes.
     *
     * Returns:
     *   - array<string, string>: An associative array mapping namespaces to their corresponding paths.
     */
    public function discoverClasses(string $directoryPath): array;

    /**
     * Validates whether the specified directory exists and is accessible.
     * Performs preliminary checks before attempting class discovery operations.
     *
     * Parameters:
     *   - string $directoryPath: The directory path to validate.
     *
     * Returns:
     *   - bool: True if the directory is valid and accessible, false otherwise.
     */
    public function validateDirectory(string $directoryPath): bool;

    /**
     * Retrieves the current discovery status and statistics.
     * Returns information about the last discovery operation including
     * the number of classes found, processing time, and any errors encountered.
     *
     * Returns:
     *   - array<string, mixed>: An array containing discovery status information.
     */
    public function getDiscoveryStatus(): array;
}