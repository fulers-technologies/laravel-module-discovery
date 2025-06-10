<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * AttributeStorageInterface defines the contract for attribute storage operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on storage operations without mixing registry or discovery logic.
 *
 * The interface provides methods to store, retrieve, and manage attribute
 * data in various storage backends including files, databases, and caches.
 */
interface AttributeStorageInterface
{
    /**
     * Stores attribute data in the configured storage backend.
     * Persists attribute information for long-term storage
     * and efficient retrieval operations.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The attribute data to store.
     *
     * Returns:
     *   - bool: True if storage was successful, false otherwise.
     */
    public function store(array $attributes): bool;

    /**
     * Retrieves all stored attribute data.
     * Returns the complete attribute dataset from storage
     * for registry initialization and bulk operations.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: All stored attribute data.
     */
    public function retrieve(): array;

    /**
     * Checks if the storage backend is available and accessible.
     * Validates that the storage system can be used for
     * attribute persistence operations.
     *
     * Returns:
     *   - bool: True if storage is available, false otherwise.
     */
    public function isAvailable(): bool;

    /**
     * Gets the storage backend type identifier.
     * Returns a string identifying the type of storage backend
     * being used for attribute persistence.
     *
     * Returns:
     *   - string: The storage backend type (e.g., 'file', 'database', 'cache').
     */
    public function getStorageType(): string;

    /**
     * Gets storage performance metrics.
     * Returns information about storage performance including
     * read/write times, storage size, and operation counts.
     *
     * Returns:
     *   - array<string, mixed>: Array of storage performance metrics.
     */
    public function getStorageMetrics(): array;
}