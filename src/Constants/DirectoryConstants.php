<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Constants;

/**
 * DirectoryConstants defines all directory-related constant values used throughout
 * the module discovery system. This class centralizes directory path configurations
 * to prevent hardcoding and ensure consistent directory references across the application.
 *
 * The constants include default module directories, file extensions, and path patterns
 * that are commonly used during the class discovery and namespace registration process.
 */
final class DirectoryConstants
{
    /**
     * Default modules directory relative to the Laravel application base path.
     * This represents the standard location where modular components are stored
     * and will be scanned for automatic class discovery.
     */
    public const DEFAULT_MODULES_DIRECTORY = 'app/Modules';

    /**
     * PHP file extension used for filtering files during directory scanning.
     * Only files with this extension will be processed for namespace extraction
     * and class discovery operations.
     */
    public const PHP_FILE_EXTENSION = 'php';

    /**
     * Vendor directory name where Composer dependencies are installed.
     * This directory contains the autoload.php file required for accessing
     * the Composer ClassLoader instance.
     */
    public const VENDOR_DIRECTORY = 'vendor';

    /**
     * Composer autoload file name within the vendor directory.
     * This file provides access to the Composer ClassLoader instance
     * needed for registering new namespace mappings.
     */
    public const COMPOSER_AUTOLOAD_FILE = 'autoload.php';

    /**
     * Directory separator pattern used for cross-platform path operations.
     * Ensures consistent path handling regardless of the operating system
     * by using the appropriate directory separator character.
     */
    public const DIRECTORY_SEPARATOR = DIRECTORY_SEPARATOR;

    /**
     * Pattern for identifying hidden directories and files.
     * Used to skip hidden system files and directories during the
     * recursive directory scanning process.
     */
    public const HIDDEN_FILE_PATTERN = '/^\./';

    /**
     * Maximum directory depth for recursive scanning operations.
     * Prevents infinite recursion and limits the scanning depth
     * to maintain reasonable performance during discovery operations.
     */
    public const MAX_SCAN_DEPTH = 10;

    /**
     * Configuration file name for module discovery settings.
     * The name of the configuration file that contains all
     * module discovery options and settings.
     */
    public const CONFIG_FILE_NAME = 'module-discovery';

    /**
     * Default suggested directories when modules directory is not found.
     * Array of common directory names that might contain modules
     * to suggest to users when the default directory doesn't exist.
     *
     * @var array<string>
     */
    public const DEFAULT_SUGGESTED_DIRECTORIES = [
        'app/Modules',
        'modules',
        'src/Modules',
        'packages',
        'app/Components',
        'src/Components',
    ];

    /**
     * Excluded directory names that should be skipped during scanning.
     * Array of directory names that typically don't contain modules
     * and should be excluded from the discovery process.
     *
     * @var array<string>
     */
    public const DEFAULT_EXCLUDED_DIRECTORIES = [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap',
        'public',
        '.git',
        '.svn',
        'tests',
        'Tests',
        'database',
        'resources',
        'lang',
    ];
}
