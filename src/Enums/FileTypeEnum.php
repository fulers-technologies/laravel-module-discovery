<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Enums;

/**
 * FileTypeEnum defines the supported file types for module discovery operations.
 * This enumeration provides type-safe file type identification that determines
 * which files should be processed during the class discovery scanning process.
 *
 * The enum ensures consistent file type handling and prevents the use of
 * magic strings for file extension comparisons throughout the system.
 */
enum FileTypeEnum: string
{
    /**
     * PHP source file type with standard .php extension.
     * This is the primary file type processed during discovery operations
     * as it contains the classes and namespaces to be registered.
     */
    case PHP = 'php';

    /**
     * PHP include file type with .inc extension.
     * These files may contain PHP code that should be included
     * in the discovery process for complete namespace mapping.
     */
    case PHP_INCLUDE = 'inc';

    /**
     * PHP template file type with .phtml extension.
     * Template files that may contain PHP classes or functions
     * requiring autoloader registration for proper functionality.
     */
    case PHP_TEMPLATE = 'phtml';

    /**
     * Configuration file type that should be excluded from discovery.
     * These files typically contain configuration data rather than
     * classes and should not be processed for namespace extraction.
     */
    case CONFIG = 'json';

    /**
     * Documentation file type that should be excluded from discovery.
     * Markdown and text files containing documentation should not
     * be processed during the class discovery operations.
     */
    case DOCUMENTATION = 'md';

    /**
     * Determines if the file type should be processed during discovery.
     * Returns true for file types that may contain PHP classes
     * and namespaces requiring autoloader registration.
     *
     * Returns:
     *   - bool: True if the file type should be processed, false otherwise.
     */
    public function shouldProcess(): bool
    {
        return match ($this) {
            self::PHP, self::PHP_INCLUDE, self::PHP_TEMPLATE => true,
            self::CONFIG, self::DOCUMENTATION => false,
        };
    }

    /**
     * Gets the MIME type associated with the file type.
     * Provides the appropriate MIME type string for the file extension
     * which can be useful for file validation and processing operations.
     *
     * Returns:
     *   - string: The MIME type string for the file type.
     */
    public function getMimeType(): string
    {
        return match ($this) {
            self::PHP, self::PHP_INCLUDE, self::PHP_TEMPLATE => 'application/x-php',
            self::CONFIG => 'application/json',
            self::DOCUMENTATION => 'text/markdown',
        };
    }

    /**
     * Creates a FileTypeEnum instance from a file extension.
     * Analyzes the provided file extension and returns the appropriate
     * enum case, or null if the extension is not recognized.
     *
     * Parameters:
     *   - string $extension: The file extension to analyze (without dot).
     *
     * Returns:
     *   - FileTypeEnum|null: The matching enum case or null if not found.
     */
    public static function fromExtension(string $extension): ?self
    {
        $normalizedExtension = strtolower(trim($extension, '.'));
        
        return match ($normalizedExtension) {
            'php' => self::PHP,
            'inc' => self::PHP_INCLUDE,
            'phtml' => self::PHP_TEMPLATE,
            'json' => self::CONFIG,
            'md', 'markdown' => self::DOCUMENTATION,
            default => null,
        };
    }
}