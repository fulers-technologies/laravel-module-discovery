<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Enums;

/**
 * AttributeStorageTypeEnum defines the supported storage types for attribute persistence.
 * This enumeration provides type-safe storage type identification that determines
 * which storage backend should be used for attribute data persistence.
 *
 * The enum ensures consistent storage type handling and prevents the use of
 * magic strings for storage type comparisons throughout the system.
 */
enum AttributeStorageTypeEnum: string
{
    /**
     * JSON file storage backend.
     * Stores attribute data in JSON format files for simple
     * file-based persistence with human-readable format.
     */
    case JSON_FILE = 'json_file';

    /**
     * PHP array file storage backend.
     * Stores attribute data as PHP array files for fast
     * loading and native PHP data structure support.
     */
    case PHP_FILE = 'php_file';

    /**
     * Database storage backend.
     * Stores attribute data in database tables for scalable
     * and queryable attribute persistence.
     */
    case DATABASE = 'database';

    /**
     * Cache storage backend.
     * Stores attribute data in cache systems for high-performance
     * temporary storage with automatic expiration.
     */
    case CACHE = 'cache';

    /**
     * Memory storage backend.
     * Stores attribute data in memory for ultra-fast access
     * during single request lifecycle.
     */
    case MEMORY = 'memory';

    /**
     * Redis storage backend.
     * Stores attribute data in Redis for distributed
     * high-performance persistent storage.
     */
    case REDIS = 'redis';

    /**
     * Gets the human-readable description for the storage type.
     * Provides user-friendly text that explains the storage type
     * for configuration, logging, and display purposes.
     *
     * Returns:
     *   - string: A descriptive message explaining the storage type.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::JSON_FILE => 'JSON file storage for human-readable attribute persistence',
            self::PHP_FILE => 'PHP array file storage for fast native data structure loading',
            self::DATABASE => 'Database storage for scalable and queryable attribute persistence',
            self::CACHE => 'Cache storage for high-performance temporary attribute storage',
            self::MEMORY => 'Memory storage for ultra-fast single-request attribute access',
            self::REDIS => 'Redis storage for distributed high-performance persistence',
        };
    }

    /**
     * Determines if the storage type is persistent.
     * Returns true for storage types that persist data beyond
     * the current request or application lifecycle.
     *
     * Returns:
     *   - bool: True if the storage type is persistent, false otherwise.
     */
    public function isPersistent(): bool
    {
        return match ($this) {
            self::JSON_FILE,
            self::PHP_FILE,
            self::DATABASE,
            self::REDIS => true,
            self::CACHE,
            self::MEMORY => false,
        };
    }

    /**
     * Determines if the storage type supports querying.
     * Returns true for storage types that support complex
     * querying and filtering operations.
     *
     * Returns:
     *   - bool: True if the storage type supports querying, false otherwise.
     */
    public function supportsQuerying(): bool
    {
        return match ($this) {
            self::DATABASE,
            self::REDIS => true,
            self::JSON_FILE,
            self::PHP_FILE,
            self::CACHE,
            self::MEMORY => false,
        };
    }

    /**
     * Gets the recommended file extension for file-based storage.
     * Returns the appropriate file extension for storage types
     * that use file-based persistence.
     *
     * Returns:
     *   - string|null: The file extension or null for non-file storage.
     */
    public function getFileExtension(): ?string
    {
        return match ($this) {
            self::JSON_FILE => 'json',
            self::PHP_FILE => 'php',
            self::DATABASE,
            self::CACHE,
            self::MEMORY,
            self::REDIS => null,
        };
    }

    /**
     * Gets the performance characteristics of the storage type.
     * Returns information about the performance profile including
     * read speed, write speed, and scalability characteristics.
     *
     * Returns:
     *   - array<string, string>: Array of performance characteristics.
     */
    public function getPerformanceProfile(): array
    {
        return match ($this) {
            self::JSON_FILE => [
                'read_speed' => 'medium',
                'write_speed' => 'medium',
                'scalability' => 'low',
                'memory_usage' => 'low',
            ],
            self::PHP_FILE => [
                'read_speed' => 'fast',
                'write_speed' => 'medium',
                'scalability' => 'low',
                'memory_usage' => 'low',
            ],
            self::DATABASE => [
                'read_speed' => 'medium',
                'write_speed' => 'medium',
                'scalability' => 'high',
                'memory_usage' => 'medium',
            ],
            self::CACHE => [
                'read_speed' => 'very_fast',
                'write_speed' => 'fast',
                'scalability' => 'medium',
                'memory_usage' => 'high',
            ],
            self::MEMORY => [
                'read_speed' => 'ultra_fast',
                'write_speed' => 'ultra_fast',
                'scalability' => 'low',
                'memory_usage' => 'very_high',
            ],
            self::REDIS => [
                'read_speed' => 'very_fast',
                'write_speed' => 'fast',
                'scalability' => 'high',
                'memory_usage' => 'medium',
            ],
        };
    }
}
