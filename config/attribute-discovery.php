<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Attribute Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for PHP attribute discovery
    | including storage settings, processing limits, validation rules, and
    | performance optimization settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable Attribute Discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the system will scan for and register PHP attributes
    | from classes in the configured modules directory.
    |
    */
    'enable_discovery' => true,

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for attribute data persistence including storage type,
    | file paths, and storage-specific options.
    |
    */
    'storage' => [
        /*
        | Storage type for attribute data persistence.
        | Supported types: json_file, php_file, database, cache, memory, redis
        */
        'type' => 'json_file',

        /*
        | Storage path for file-based attribute storage.
        | Path where attribute data files will be stored (relative to base path).
        */
        'path' => 'storage/framework/attributes',

        /*
        | Database table name for database storage.
        | Table name used when storing attributes in database.
        */
        'table' => 'attribute_registry',

        /*
        | Cache key prefix for cache-based storage.
        | Prefix used for cache keys when using cache storage.
        */
        'cache_prefix' => 'laravel_module_discovery_attributes',

        /*
        | Redis connection name for Redis storage.
        | Redis connection to use when storing attributes in Redis.
        */
        'redis_connection' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings that control attribute discovery processing including
    | batch sizes, depth limits, and performance optimization.
    |
    */
    'processing' => [
        /*
        | Maximum number of classes to process in a single batch.
        | Limits batch size for memory management during discovery.
        */
        'max_batch_size' => 1000,

        /*
        | Maximum depth for attribute parameter analysis.
        | Limits recursion depth when analyzing attribute parameters.
        */
        'max_attribute_depth' => 5,

        /*
        | Timeout for attribute discovery operations in seconds.
        | Maximum time allowed for discovery operations.
        */
        'timeout_seconds' => 300,

        /*
        | Memory limit for attribute discovery operations.
        | Memory limit setting for discovery operations.
        */
        'memory_limit' => '256M',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching discovered attributes to improve performance
    | during subsequent discovery operations.
    |
    */
    'cache' => [
        /*
        | Enable caching of discovered attributes.
        | When enabled, attributes will be cached for faster retrieval.
        */
        'enabled' => true,

        /*
        | Cache TTL for attribute data in seconds.
        | How long attribute data should be cached for performance.
        */
        'ttl' => 3600,

        /*
        | Cache store to use for attribute caching.
        | Laravel cache store name for attribute caching.
        */
        'store' => 'file',

        /*
        | Maximum cache size for in-memory caching.
        | Limits the number of cached items to prevent memory issues.
        */
        'max_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation and Filtering
    |--------------------------------------------------------------------------
    |
    | Configuration for validating and filtering discovered attributes
    | including exclusion rules and validation criteria.
    |
    */
    'validation' => [
        /*
        | Enable validation of discovered attributes.
        | When enabled, attributes will be validated before registration.
        */
        'enabled' => true,

        /*
        | Array of attribute class names to exclude from discovery.
        | These attribute types will be ignored during scanning.
        */
        'excluded_attribute_types' => [
            'Attribute',
            'Override',
            'SensitiveParameter',
            'AllowDynamicProperties',
            'ReturnTypeWillChange',
        ],

        /*
        | Array of class names to exclude from attribute discovery.
        | These classes will be skipped entirely during scanning.
        */
        'excluded_classes' => [
            // Add specific classes to exclude
        ],

        /*
        | Array of namespace prefixes to exclude from attribute discovery.
        | Classes in these namespaces will be skipped during scanning.
        */
        'excluded_namespaces' => [
            'Vendor\\',
            'Tests\\',
            'Database\\Migrations\\',
            'Database\\Seeders\\',
            'Database\\Factories\\',
        ],

        /*
        | Minimum number of attributes required for class registration.
        | Classes with fewer attributes will be excluded.
        */
        'min_attributes_per_class' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Collection
    |--------------------------------------------------------------------------
    |
    | Settings for collecting additional metadata about discovered
    | attributes including reflection information and context data.
    |
    */
    'metadata' => [
        /*
        | Enable metadata collection for discovered attributes.
        | When enabled, additional metadata about attributes will be collected.
        */
        'enabled' => true,

        /*
        | Include reflection information in metadata.
        | Adds detailed reflection data to attribute metadata.
        */
        'include_reflection' => true,

        /*
        | Include file information in metadata.
        | Adds file path and modification time to metadata.
        */
        'include_file_info' => true,

        /*
        | Include target information in metadata.
        | Adds information about the attribute target (class, method, property).
        */
        'include_target_info' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Settings to optimize attribute discovery performance for large
    | codebases and improve overall system responsiveness.
    |
    */
    'performance' => [
        /*
        | Enable parallel processing for large directories.
        | Uses multiple processes to scan directories simultaneously.
        */
        'enable_parallel_processing' => false,

        /*
        | Maximum number of parallel processes to use.
        | Only applies when parallel processing is enabled.
        */
        'max_parallel_processes' => 4,

        /*
        | Enable memory optimization during discovery.
        | Reduces memory footprint by clearing caches and optimizing data structures.
        */
        'optimize_memory_usage' => true,

        /*
        | Enable garbage collection between batches.
        | Forces garbage collection to free memory between processing batches.
        */
        'enable_gc_between_batches' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for logging attribute discovery operations and
    | monitoring performance metrics.
    |
    */
    'logging' => [
        /*
        | Enable detailed logging of discovery operations.
        | Logs will include processing statistics and error information.
        */
        'enabled' => false,

        /*
        | Log level for discovery operations.
        | Available levels: emergency, alert, critical, error, warning, notice, info, debug
        */
        'level' => 'info',

        /*
        | Log channel to use for discovery operations.
        | Must be a valid Laravel log channel defined in logging.php
        */
        'channel' => 'single',

        /*
        | Enable performance monitoring.
        | Collects detailed performance metrics for optimization.
        */
        'enable_performance_monitoring' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    |
    | Configuration options specifically for development and testing
    | environments to aid in debugging and package development.
    |
    */
    'development' => [
        /*
        | Enable debug mode for detailed error reporting.
        | Shows stack traces and additional debugging information.
        */
        'debug_mode' => false,

        /*
        | Enable dry run mode for testing without actual registration.
        | Discovery will run but attributes won't be registered.
        */
        'dry_run_mode' => false,

        /*
        | Save discovery results to file for analysis.
        | Results will be saved in JSON format to the specified path.
        */
        'save_results_to_file' => false,

        /*
        | Path where discovery results should be saved.
        | Only used when save_results_to_file is enabled.
        */
        'results_file_path' => 'storage/logs/attribute-discovery-results.json',
    ],
];
