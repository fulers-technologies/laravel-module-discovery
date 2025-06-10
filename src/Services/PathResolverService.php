<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Constants\DirectoryConstants;
use LaravelModuleDiscovery\ComposerHook\Interfaces\PathResolverInterface;

/**
 * PathResolverService implements path resolution and normalization operations.
 * This service handles the conversion of relative paths to absolute paths,
 * normalizes path formats for cross-platform compatibility, and provides
 * directory path extraction functionality for the discovery system.
 */
class PathResolverService implements PathResolverInterface
{
    /**
     * Cache of resolved paths to improve performance.
     * Stores previously resolved path information to avoid
     * redundant path resolution operations during discovery.
     *
     * @var array<string, string>
     */
    private array $pathCache = [];

    /**
     * The base path used for relative path resolution.
     * Represents the root directory that serves as the reference
     * point for converting relative paths to absolute paths.
     */
    private ?string $basePath;

    /**
     * Creates a new PathResolverService instance.
     * Initializes the service with an optional base path for
     * relative path resolution operations.
     *
     * Parameters:
     *   - string|null $basePath: Optional base path for path resolution.
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath;
    }

    /**
     * Creates a new PathResolverService instance using static factory method.
     * Provides a convenient way to instantiate the service with optional
     * base path configuration without using the new keyword.
     *
     * Parameters:
     *   - string|null $basePath: Optional base path for path resolution.
     *
     * Returns:
     *   - static: A new PathResolverService instance.
     */
    public static function make(?string $basePath = null): static
    {
        return new static($basePath);
    }

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
    public function resolveAbsolutePath(string $relativePath, ?string $basePath = null): string
    {
        $cacheKey = $relativePath . '|' . ($basePath ?? $this->basePath ?? '');
        
        if (isset($this->pathCache[$cacheKey])) {
            return $this->pathCache[$cacheKey];
        }

        // Return as-is if already absolute
        if ($this->isAbsolutePath($relativePath)) {
            $resolved = $this->normalizePath($relativePath);
            $this->pathCache[$cacheKey] = $resolved;
            return $resolved;
        }

        $baseDirectory = $basePath ?? $this->basePath ?? $this->getDefaultBasePath();
        $absolutePath = $baseDirectory . DirectoryConstants::DIRECTORY_SEPARATOR . $relativePath;
        
        $resolved = $this->normalizePath($absolutePath);
        $this->pathCache[$cacheKey] = $resolved;
        
        return $resolved;
    }

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
    public function normalizePath(string $path): string
    {
        // Convert all separators to the system separator
        $normalizedPath = str_replace(['/', '\\'], DirectoryConstants::DIRECTORY_SEPARATOR, $path);
        
        // Remove double separators
        $normalizedPath = preg_replace('/[' . preg_quote(DirectoryConstants::DIRECTORY_SEPARATOR, '/') . ']+/', DirectoryConstants::DIRECTORY_SEPARATOR, $normalizedPath);
        
        // Remove trailing separator (except for root)
        if (strlen($normalizedPath) > 1) {
            $normalizedPath = rtrim($normalizedPath, DirectoryConstants::DIRECTORY_SEPARATOR);
        }
        
        return $normalizedPath;
    }

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
    public function getDirectoryPath(string $filePath): string
    {
        $normalizedPath = $this->normalizePath($filePath);
        return dirname($normalizedPath);
    }

    /**
     * Determines if a path is absolute.
     * Checks whether the provided path is already absolute
     * and does not require base path resolution.
     *
     * Parameters:
     *   - string $path: The path to check.
     *
     * Returns:
     *   - bool: True if the path is absolute, false otherwise.
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix/Linux absolute path
        if (str_starts_with($path, '/')) {
            return true;
        }
        
        // Windows absolute path (C:\ or similar)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }
        
        return false;
    }

    /**
     * Gets the default base path for path resolution.
     * Returns the appropriate base directory based on the
     * application context and available environment information.
     *
     * Returns:
     *   - string: The default base path for resolution operations.
     */
    private function getDefaultBasePath(): string
    {
        // Try to get Laravel base path if available
        if (function_exists('base_path')) {
            return base_path();
        }
        
        // Fallback to current working directory
        return getcwd() ?: __DIR__;
    }

    /**
     * Clears the path resolution cache.
     * Removes all cached path resolution results to free memory
     * and ensure fresh resolution during discovery operations.
     */
    public function clearCache(): void
    {
        $this->pathCache = [];
    }

    /**
     * Gets the current path cache statistics.
     * Returns information about cached path resolutions
     * for performance monitoring and debugging purposes.
     *
     * Returns:
     *   - array<string, mixed>: Cache statistics including size and entries.
     */
    public function getCacheStats(): array
    {
        return [
            'cache_size' => count($this->pathCache),
            'cached_paths' => array_keys($this->pathCache),
            'base_path' => $this->basePath,
        ];
    }

    /**
     * Sets the base path for path resolution operations.
     * Updates the base directory used for converting relative
     * paths to absolute paths during discovery operations.
     *
     * Parameters:
     *   - string $basePath: The new base path to use for resolution.
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = $this->normalizePath($basePath);
        $this->clearCache(); // Clear cache since base path changed
    }
}