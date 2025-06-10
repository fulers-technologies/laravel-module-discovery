<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Constants;

/**
 * AttributeConstants defines all attribute-related constant values used throughout
 * the attribute discovery system. This class centralizes attribute configurations
 * to prevent hardcoding and ensure consistent attribute handling across the application.
 *
 * The constants include default storage paths, cache configurations, and attribute
 * processing settings that are commonly used during discovery and registration.
 */
final class AttributeConstants
{
    /**
     * Default storage directory for attribute data files.
     * This represents the standard location where attribute data
     * will be stored for file-based storage backends.
     */
    public const DEFAULT_STORAGE_DIRECTORY = 'storage/framework/attributes';

    /**
     * Default filename for JSON attribute storage.
     * The standard filename used when storing attributes
     * in JSON format for file-based persistence.
     */
    public const DEFAULT_JSON_FILENAME = 'attributes.json';

    /**
     * Default filename for PHP attribute storage.
     * The standard filename used when storing attributes
     * in PHP array format for file-based persistence.
     */
    public const DEFAULT_PHP_FILENAME = 'attributes.php';

    /**
     * Cache key prefix for attribute data.
     * Used to namespace cache keys for attribute data
     * to prevent conflicts with other cached data.
     */
    public const CACHE_KEY_PREFIX = 'laravel_module_discovery_attributes';

    /**
     * Default cache TTL for attribute data in seconds.
     * Standard cache duration for attribute data that doesn't
     * change frequently during normal application operation.
     */
    public const DEFAULT_CACHE_TTL = 3600; // 1 hour

    /**
     * Maximum number of attributes to process in a single batch.
     * Limits the batch size for attribute processing operations
     * to maintain reasonable memory usage and performance.
     */
    public const MAX_BATCH_SIZE = 1000;

    /**
     * Maximum depth for attribute parameter analysis.
     * Limits the depth of attribute parameter inspection
     * to prevent infinite recursion and excessive processing.
     */
    public const MAX_ATTRIBUTE_DEPTH = 5;

    /**
     * Default timeout for attribute discovery operations in seconds.
     * Maximum time allowed for attribute discovery operations
     * to prevent hanging during large directory scans.
     */
    public const DISCOVERY_TIMEOUT_SECONDS = 300; // 5 minutes

    /**
     * Registry table name for database storage.
     * The database table name used for storing attribute
     * data when using database storage backend.
     */
    public const REGISTRY_TABLE_NAME = 'attribute_registry';

    /**
     * Metadata table name for database storage.
     * The database table name used for storing attribute
     * metadata and discovery statistics.
     */
    public const METADATA_TABLE_NAME = 'attribute_metadata';

    /**
     * Default storage backend type.
     * The default storage backend used when no specific
     * storage type is configured in the application.
     */
    public const DEFAULT_STORAGE_TYPE = 'json_file';

    /**
     * Supported attribute target types.
     * Array of reflection types that can have attributes
     * and should be processed during discovery operations.
     *
     * @var array<string>
     */
    public const SUPPORTED_TARGET_TYPES = [
        'ReflectionClass',
        'ReflectionMethod',
        'ReflectionProperty',
        'ReflectionParameter',
        'ReflectionClassConstant',
        'ReflectionFunction',
    ];

    /**
     * Excluded attribute classes from discovery.
     * Array of attribute class names that should be excluded
     * from discovery operations to avoid system attributes.
     *
     * @var array<string>
     */
    public const EXCLUDED_ATTRIBUTE_CLASSES = [
        'Attribute',
        'Override',
        'SensitiveParameter',
        'AllowDynamicProperties',
        'ReturnTypeWillChange',
    ];

    /**
     * Default attribute discovery configuration.
     * Standard configuration values for attribute discovery
     * operations including performance and behavior settings.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_DISCOVERY_CONFIG = [
        'enable_caching' => true,
        'cache_ttl' => self::DEFAULT_CACHE_TTL,
        'max_batch_size' => self::MAX_BATCH_SIZE,
        'max_depth' => self::MAX_ATTRIBUTE_DEPTH,
        'timeout' => self::DISCOVERY_TIMEOUT_SECONDS,
        'storage_type' => self::DEFAULT_STORAGE_TYPE,
        'enable_metadata' => true,
        'enable_validation' => true,
    ];

    /**
     * Performance monitoring thresholds.
     * Threshold values for performance monitoring and
     * alerting during attribute discovery operations.
     *
     * @var array<string, int>
     */
    public const PERFORMANCE_THRESHOLDS = [
        'max_processing_time' => 60, // seconds
        'max_memory_usage' => 128 * 1024 * 1024, // 128MB
        'max_attributes_per_class' => 50,
        'max_classes_per_batch' => 100,
    ];

    /**
     * Storage file permissions for file-based backends.
     * Default file permissions used when creating attribute
     * storage files for security and access control.
     */
    public const STORAGE_FILE_PERMISSIONS = 0644;

    /**
     * Storage directory permissions for file-based backends.
     * Default directory permissions used when creating attribute
     * storage directories for security and access control.
     */
    public const STORAGE_DIRECTORY_PERMISSIONS = 0755;

    /**
     * Backup file suffix for storage operations.
     * Suffix added to backup files created during storage
     * operations to prevent data loss during updates.
     */
    public const BACKUP_FILE_SUFFIX = '.backup';

    /**
     * Lock file suffix for concurrent access control.
     * Suffix added to lock files used to prevent concurrent
     * access during attribute storage operations.
     */
    public const LOCK_FILE_SUFFIX = '.lock';

    /**
     * Maximum lock file age in seconds.
     * Maximum age for lock files before they are considered
     * stale and can be safely removed or ignored.
     */
    public const MAX_LOCK_FILE_AGE = 300; // 5 minutes
}
