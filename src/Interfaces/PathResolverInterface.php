<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * PathResolverInterface defines the contract for path resolution operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on path-related operations without mixing other system concerns.
 *
 * The interface provides methods to resolve absolute paths, normalize path formats,
 * and handle various path manipulation requirements for the discovery system.
 */
interface PathResolverInterface
{
    /**
     * Resolves a relative path to its absolute equivalent.
     * Converts relative path references to absolute paths based on the
     * application's base directory or specified root path.
     *
     * Parameters:
     *   - string $relativePath: The relative path to resolve.
     *   - string|null $basePath: Optional base path for resolution (defaults to app base path).
     *
     * Returns:
     *   - string: The resolved absolute path.
     */
    public function resolveAbsolutePath(string $relativePath, ?string $basePath = null): string;

    /**
     * Normalizes path separators and format for cross-platform compatibility.
     * Ensures consistent path formatting regardless of the operating system
     * by standardizing directory separators and removing redundant elements.
     *
     * Parameters:
     *   - string $path: The path to normalize.
     *
     * Returns:
     *   - string: The normalized path string.
     */
    public function normalizePath(string $path): string;

    /**
     * Extracts the directory path from a full file path.
     * Returns the directory portion of a file path, removing the filename
     * and maintaining the directory structure for autoloader registration.
     *
     * Parameters:
     *   - string $filePath: The complete file path to process.
     *
     * Returns:
     *   - string: The directory path without the filename.
     */
    public function getDirectoryPath(string $filePath): string;
}