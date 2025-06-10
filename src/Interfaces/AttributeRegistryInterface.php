<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * AttributeRegistryInterface defines the contract for attribute registry operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on attribute registration and storage without mixing discovery logic.
 *
 * The interface provides methods to register discovered attributes, manage
 * attribute storage, and handle registry lifecycle operations.
 */
interface AttributeRegistryInterface
{
    /**
     * Registers discovered attributes in the registry.
     * Stores attribute information in the configured storage backend
     * for efficient retrieval and usage.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The attributes to register.
     *
     * Returns:
     *   - bool: True if registration was successful, false otherwise.
     */
    public function registerAttributes(array $attributes): bool;

    /**
     * Retrieves registered attributes by class name.
     * Returns attribute information for a specific class
     * from the registry storage.
     *
     * Parameters:
     *   - string $className: The class name to retrieve attributes for.
     *
     * Returns:
     *   - array<string, mixed>: Array of attributes for the specified class.
     */
    public function getAttributesByClass(string $className): array;

    /**
     * Retrieves all registered attributes by attribute type.
     * Returns all classes that have a specific attribute type
     * registered in the system.
     *
     * Parameters:
     *   - string $attributeType: The attribute class name to search for.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: Array of classes with the specified attribute.
     */
    public function getAttributesByType(string $attributeType): array;

    /**
     * Clears all registered attributes from the registry.
     * Removes all attribute information from storage
     * for fresh registration operations.
     *
     * Returns:
     *   - bool: True if clearing was successful, false otherwise.
     */
    public function clearRegistry(): bool;

    /**
     * Gets registry statistics and metadata.
     * Returns information about the current state of the registry
     * including counts, storage size, and performance metrics.
     *
     * Returns:
     *   - array<string, mixed>: Array of registry statistics.
     */
    public function getRegistryStats(): array;
}