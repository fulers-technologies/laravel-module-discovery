<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * AttributeDiscoveryInterface defines the contract for attribute discovery operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on attribute discovery functionality without mixing other concerns.
 *
 * The interface provides methods to discover attributes from PHP classes,
 * handle attribute metadata, and manage the discovery process lifecycle.
 */
interface AttributeDiscoveryInterface
{
    /**
     * Discovers attributes within the specified directory path.
     * Scans through the directory structure to identify PHP classes
     * and extract their attribute information for registration.
     *
     * Parameters:
     *   - string $directoryPath: The absolute path to the directory to scan for attributes.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: An associative array mapping classes to their attributes.
     */
    public function discoverAttributes(string $directoryPath): array;

    /**
     * Extracts attributes from a specific PHP class.
     * Analyzes a single class to identify and extract all attribute
     * information including parameters and metadata.
     *
     * Parameters:
     *   - string $className: The fully qualified class name to analyze.
     *
     * Returns:
     *   - array<string, mixed>: Array of attribute information for the class.
     */
    public function extractClassAttributes(string $className): array;

    /**
     * Validates discovered attributes against configuration rules.
     * Ensures attributes meet validation criteria and filtering
     * requirements before registration.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The discovered attributes to validate.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: Validated and filtered attributes.
     */
    public function validateAttributes(array $attributes): array;

    /**
     * Retrieves the current attribute discovery status and statistics.
     * Returns information about the last discovery operation including
     * the number of attributes found, processing time, and any errors encountered.
     *
     * Returns:
     *   - array<string, mixed>: An array containing discovery status information.
     */
    public function getDiscoveryStatus(): array;
}