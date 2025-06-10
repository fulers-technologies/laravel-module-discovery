<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the Laravel Module
    | Discovery Composer Hook package. These settings control how the package
    | discovers, processes, and registers module namespaces with Composer's
    | autoloader system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Modules Directory
    |--------------------------------------------------------------------------
    |
    | The default directory path where the package will scan for modules.
    | This path is relative to the Laravel application's base directory.
    | You can override this using the --path option in the Artisan command.
    |
    */
    'default_modules_directory' => env('MODULE_DISCOVERY_DEFAULT_DIR', 'app/Modules'),

    /*
    |--------------------------------------------------------------------------
    | Supported File Extensions
    |--------------------------------------------------------------------------
    |
    | Array of file extensions that should be processed during module discovery.
    | Only files with these extensions will be scanned for namespace extraction.
    | The extensions should be specified without the leading dot.
    |
    */
    'supported_extensions' => [
        'php',
        'inc',
        'phtml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options that control the discovery process behavior,
    | including performance settings, scanning limits, and processing options.
    |
    */
    'discovery' => [
        /*
        | Maximum directory depth for recursive scanning operations.
        | Prevents infinite recursion and limits scanning depth for performance.
        */
        'max_scan_depth' => env('MODULE_DISCOVERY_MAX_DEPTH', 10),

        /*
        | Maximum number of tokens to examine for namespace detection.
        | Limits parsing depth to improve performance for large PHP files.
        */
        'max_tokens_to_examine' => env('MODULE_DISCOVERY_MAX_TOKENS', 100),

        /*
        | Timeout value for discovery operations in seconds.
        | Maximum time allowed for module discovery to prevent hanging.
        */
        'timeout_seconds' => env('MODULE_DISCOVERY_TIMEOUT', 300),

        /*
        | Enable or disable caching of namespace extraction results.
        | Caching improves performance when processing the same files multiple times.
        */
        'enable_caching' => env('MODULE_DISCOVERY_ENABLE_CACHE', true),

        /*
        | Skip hidden files and directories during scanning.
        | Hidden files/directories start with a dot (.) character.
        */
        'skip_hidden_files' => env('MODULE_DISCOVERY_SKIP_HIDDEN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Validation
    |--------------------------------------------------------------------------
    |
    | Settings that control how extracted namespaces are validated before
    | registration with the Composer autoloader system.
    |
    */
    'validation' => [
        /*
        | Enable strict namespace validation according to PSR-4 standards.
        | When enabled, only namespaces that strictly follow PSR-4 will be registered.
        */
        'strict_psr4_validation' => env('MODULE_DISCOVERY_STRICT_PSR4', true),

        /*
        | Minimum namespace length required for registration.
        | Namespaces shorter than this length will be rejected.
        */
        'min_namespace_length' => env('MODULE_DISCOVERY_MIN_NS_LENGTH', 3),

        /*
        | Maximum namespace length allowed for registration.
        | Namespaces longer than this length will be rejected.
        */
        'max_namespace_length' => env('MODULE_DISCOVERY_MAX_NS_LENGTH', 255),

        /*
        | Array of namespace prefixes that should be excluded from discovery.
        | Useful for excluding vendor namespaces or system directories.
        */
        'excluded_namespace_prefixes' => [
            'Vendor\\',
            'Tests\\',
            'Database\\',
        ],

        /*
        | Array of directory names that should be excluded from scanning.
        | These directories will be completely skipped during discovery.
        */
        'excluded_directories' => [
            'vendor',
            'node_modules',
            'storage',
            'bootstrap',
            'public',
            '.git',
            '.svn',
            'tests',
            'Tests',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer Integration
    |--------------------------------------------------------------------------
    |
    | Configuration options for integrating with Composer's autoloader
    | and managing PSR-4 namespace registrations.
    |
    */
    'composer' => [
        /*
        | Enable automatic registration of discovered namespaces.
        | When disabled, namespaces will be discovered but not registered.
        */
        'auto_register_namespaces' => env('MODULE_DISCOVERY_AUTO_REGISTER', true),

        /*
        | Force re-registration of existing namespaces.
        | When enabled, existing namespace mappings will be overwritten.
        */
        'force_reregistration' => env('MODULE_DISCOVERY_FORCE_REREGISTER', false),

        /*
        | Enable batch registration of multiple namespaces.
        | Improves performance when registering many namespaces simultaneously.
        */
        'enable_batch_registration' => env('MODULE_DISCOVERY_BATCH_REGISTER', true),

        /*
        | Automatically apply registrations after discovery.
        | When enabled, the autoloader will be refreshed after registration.
        */
        'auto_apply_registrations' => env('MODULE_DISCOVERY_AUTO_APPLY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Output
    |--------------------------------------------------------------------------
    |
    | Configuration for logging discovery operations and controlling
    | command output verbosity and formatting.
    |
    */
    'logging' => [
        /*
        | Enable detailed logging of discovery operations.
        | Logs will include processing statistics and error information.
        */
        'enable_detailed_logging' => env('MODULE_DISCOVERY_DETAILED_LOG', false),

        /*
        | Log level for discovery operations.
        | Available levels: emergency, alert, critical, error, warning, notice, info, debug
        */
        'log_level' => env('MODULE_DISCOVERY_LOG_LEVEL', 'info'),

        /*
        | Log channel to use for discovery operations.
        | Must be a valid Laravel log channel defined in logging.php
        */
        'log_channel' => env('MODULE_DISCOVERY_LOG_CHANNEL', 'single'),

        /*
        | Enable progress indicators during discovery operations.
        | Shows progress bars and status updates during long-running operations.
        */
        'show_progress_indicators' => env('MODULE_DISCOVERY_SHOW_PROGRESS', true),

        /*
        | Default output verbosity level for the discovery command.
        | Controls how much information is displayed during command execution.
        */
        'default_verbosity' => env('MODULE_DISCOVERY_VERBOSITY', 'normal'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for error handling behavior during discovery operations,
    | including retry logic and failure recovery options.
    |
    */
    'error_handling' => [
        /*
        | Continue discovery on file processing errors.
        | When enabled, individual file errors won't stop the entire discovery process.
        */
        'continue_on_errors' => env('MODULE_DISCOVERY_CONTINUE_ON_ERROR', true),

        /*
        | Maximum number of file processing errors before stopping discovery.
        | Set to 0 for unlimited errors (when continue_on_errors is true).
        */
        'max_errors_before_stop' => env('MODULE_DISCOVERY_MAX_ERRORS', 10),

        /*
        | Enable retry logic for failed operations.
        | Failed namespace extractions will be retried up to the specified limit.
        */
        'enable_retry_logic' => env('MODULE_DISCOVERY_ENABLE_RETRY', false),

        /*
        | Maximum number of retry attempts for failed operations.
        | Only applies when enable_retry_logic is true.
        */
        'max_retry_attempts' => env('MODULE_DISCOVERY_MAX_RETRIES', 3),

        /*
        | Delay between retry attempts in milliseconds.
        | Prevents overwhelming the system during retry operations.
        */
        'retry_delay_ms' => env('MODULE_DISCOVERY_RETRY_DELAY', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Settings to optimize discovery performance for large codebases
    | and improve overall system responsiveness.
    |
    */
    'performance' => [
        /*
        | Enable memory usage optimization during discovery.
        | Reduces memory footprint by clearing caches and optimizing data structures.
        */
        'optimize_memory_usage' => env('MODULE_DISCOVERY_OPTIMIZE_MEMORY', true),

        /*
        | Maximum memory limit for discovery operations in MB.
        | Discovery will be aborted if memory usage exceeds this limit.
        */
        'memory_limit_mb' => env('MODULE_DISCOVERY_MEMORY_LIMIT', 256),

        /*
        | Enable parallel processing for large directories.
        | Uses multiple processes to scan directories simultaneously (requires pcntl extension).
        */
        'enable_parallel_processing' => env('MODULE_DISCOVERY_PARALLEL', false),

        /*
        | Maximum number of parallel processes to use.
        | Only applies when enable_parallel_processing is true.
        */
        'max_parallel_processes' => env('MODULE_DISCOVERY_MAX_PROCESSES', 4),

        /*
        | Cache size limit for namespace extraction results.
        | Limits the number of cached results to prevent memory issues.
        */
        'cache_size_limit' => env('MODULE_DISCOVERY_CACHE_LIMIT', 1000),
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
        'debug_mode' => env('MODULE_DISCOVERY_DEBUG', false),

        /*
        | Enable dry run mode for testing without actual registration.
        | Discovery will run but namespaces won't be registered with Composer.
        */
        'dry_run_mode' => env('MODULE_DISCOVERY_DRY_RUN', false),

        /*
        | Enable profiling of discovery operations.
        | Collects detailed performance metrics for optimization.
        */
        'enable_profiling' => env('MODULE_DISCOVERY_PROFILING', false),

        /*
        | Save discovery results to file for analysis.
        | Results will be saved in JSON format to the specified path.
        */
        'save_results_to_file' => env('MODULE_DISCOVERY_SAVE_RESULTS', false),

        /*
        | Path where discovery results should be saved.
        | Only used when save_results_to_file is enabled.
        */
        'results_file_path' => env('MODULE_DISCOVERY_RESULTS_PATH', storage_path('logs/module-discovery-results.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Suggested Directories
    |--------------------------------------------------------------------------
    |
    | Array of directory suggestions to show when the default modules
    | directory is not found. These help users identify alternative locations.
    |
    */
    'suggested_directories' => [
        'app/Modules',
        'modules',
        'src/Modules',
        'packages',
        'app/Components',
        'src/Components',
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Aliases
    |--------------------------------------------------------------------------
    |
    | Alternative command names that can be used to trigger module discovery.
    | These aliases provide convenient shortcuts for common operations.
    |
    */
    'command_aliases' => [
        'module:scan',
        'modules:discover',
        'autoload:modules',
    ],
];
