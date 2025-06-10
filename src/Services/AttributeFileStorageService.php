<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use LaravelModuleDiscovery\ComposerHook\Constants\AttributeConstants;
use LaravelModuleDiscovery\ComposerHook\Enums\AttributeStorageTypeEnum;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeStorageInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;

/**
 * AttributeFileStorageService implements file-based attribute storage functionality.
 * This service handles the persistence of attribute data to file systems using
 * various formats including JSON and PHP array files for efficient storage and retrieval.
 *
 * The service provides robust file operations with backup, locking, and error
 * recovery mechanisms for reliable attribute data persistence.
 */
class AttributeFileStorageService implements AttributeStorageInterface
{
    /**
     * The storage directory path for attribute files.
     * Contains the absolute path where attribute data files
     * will be stored and retrieved.
     */
    private string $storagePath;

    /**
     * The storage type being used for file operations.
     * Determines the file format and storage strategy
     * for attribute data persistence.
     */
    private AttributeStorageTypeEnum $storageType;

    /**
     * Storage performance metrics.
     * Tracks performance information about storage operations
     * including read/write times and operation counts.
     *
     * @var array<string, mixed>
     */
    private array $storageMetrics;

    /**
     * Creates a new AttributeFileStorageService instance.
     * Initializes the service with configuration management for
     * controlling storage behavior and file operations.
     *
     * Parameters:
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly ConfigurationInterface $configuration
    ) {
        $this->initializeStorage();
    }

    /**
     * Creates a new AttributeFileStorageService instance using static factory method.
     * Provides a convenient way to instantiate the service without using the new keyword.
     *
     * Parameters:
     *   - ConfigurationInterface|null $configuration: Optional custom configuration service.
     *
     * Returns:
     *   - static: A new AttributeFileStorageService instance.
     */
    public static function make(?ConfigurationInterface $configuration = null): static
    {
        return new static(
            $configuration ?? ConfigurationService::make()
        );
    }

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
    public function store(array $attributes): bool
    {
        $this->d("💾 [ATTRIBUTE-STORAGE] Storing " . count($attributes) . " classes with attributes");

        $startTime = microtime(true);

        try {
            $this->ensureStorageDirectory();
            $filePath = $this->getStorageFilePath();

            // Create backup if file exists
            if (File::exists($filePath)) {
                $this->createBackup($filePath);
            }

            // Acquire lock for concurrent access protection
            $lockFile = $filePath . AttributeConstants::LOCK_FILE_SUFFIX;
            $lockHandle = $this->acquireLock($lockFile);

            try {
                $success = $this->writeAttributeFile($filePath, $attributes);

                if ($success) {
                    $this->updateStorageMetrics([
                        'last_write_time' => microtime(true) - $startTime,
                        'last_write_size' => File::size($filePath),
                        'write_count' => Arr::get($this->storageMetrics, 'write_count', 0) + 1,
                    ]);

                    $this->d("✅ [ATTRIBUTE-STORAGE] Successfully stored attributes to: {$filePath}");
                    return true;
                } else {
                    $this->d("❌ [ATTRIBUTE-STORAGE] Failed to write attributes to file");
                    return false;
                }

            } finally {
                $this->releaseLock($lockHandle, $lockFile);
            }

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-STORAGE] Storage operation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all stored attribute data.
     * Returns the complete attribute dataset from storage
     * for registry initialization and bulk operations.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: All stored attribute data.
     */
    public function retrieve(): array
    {
        $this->d("📖 [ATTRIBUTE-STORAGE] Retrieving stored attributes");

        $startTime = microtime(true);

        try {
            $filePath = $this->getStorageFilePath();

            if (!File::exists($filePath)) {
                $this->d("ℹ️ [ATTRIBUTE-STORAGE] Storage file does not exist, returning empty array");
                return [];
            }

            $attributes = $this->readAttributeFile($filePath);

            $this->updateStorageMetrics([
                'last_read_time' => microtime(true) - $startTime,
                'last_read_size' => File::size($filePath),
                'read_count' => Arr::get($this->storageMetrics, 'read_count', 0) + 1,
            ]);

            $this->d("✅ [ATTRIBUTE-STORAGE] Retrieved " . count($attributes) . " classes from storage");

            return $attributes;

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-STORAGE] Retrieval operation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Checks if the storage backend is available and accessible.
     * Validates that the storage system can be used for
     * attribute persistence operations.
     *
     * Returns:
     *   - bool: True if storage is available, false otherwise.
     */
    public function isAvailable(): bool
    {
        try {
            // Check if storage directory exists or can be created
            if (!File::isDirectory($this->storagePath)) {
                return $this->createStorageDirectory();
            }

            // Check if directory is writable
            if (!File::isWritable($this->storagePath)) {
                $this->d("❌ [ATTRIBUTE-STORAGE] Storage directory is not writable: {$this->storagePath}");
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-STORAGE] Storage availability check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the storage backend type identifier.
     * Returns a string identifying the type of storage backend
     * being used for attribute persistence.
     *
     * Returns:
     *   - string: The storage backend type (e.g., 'file', 'database', 'cache').
     */
    public function getStorageType(): string
    {
        return $this->storageType->value;
    }

    /**
     * Gets storage performance metrics.
     * Returns information about storage performance including
     * read/write times, storage size, and operation counts.
     *
     * Returns:
     *   - array<string, mixed>: Array of storage performance metrics.
     */
    public function getStorageMetrics(): array
    {
        $metrics = $this->storageMetrics;

        // Add current file information
        $filePath = $this->getStorageFilePath();
        if (File::exists($filePath)) {
            $metrics['file_size'] = File::size($filePath);
            $metrics['file_modified'] = File::lastModified($filePath);
        }

        $metrics['storage_path'] = $this->storagePath;
        $metrics['storage_type'] = $this->storageType->value;

        return $metrics;
    }

    /**
     * Initializes the storage configuration and settings.
     * Sets up storage paths, types, and performance tracking
     * based on configuration values.
     */
    private function initializeStorage(): void
    {
        // Determine storage type
        $storageTypeConfig = $this->configuration->get('attribute-discovery.storage.type', AttributeConstants::DEFAULT_STORAGE_TYPE);
        $this->storageType = AttributeStorageTypeEnum::from($storageTypeConfig);

        // Set up storage path
        $configuredPath = $this->configuration->get('attribute-discovery.storage.path', AttributeConstants::DEFAULT_STORAGE_DIRECTORY);
        $this->storagePath = $this->resolveStoragePath($configuredPath);

        // Initialize metrics
        $this->storageMetrics = [
            'initialized_at' => time(),
            'read_count' => 0,
            'write_count' => 0,
            'last_read_time' => 0,
            'last_write_time' => 0,
            'last_read_size' => 0,
            'last_write_size' => 0,
        ];

        $this->d("🔧 [ATTRIBUTE-STORAGE] Initialized storage: {$this->storageType->value} at {$this->storagePath}");
    }

    /**
     * Resolves the storage path to an absolute path.
     * Converts relative storage paths to absolute paths
     * based on the application base directory.
     *
     * Parameters:
     *   - string $configuredPath: The configured storage path.
     *
     * Returns:
     *   - string: The resolved absolute storage path.
     */
    private function resolveStoragePath(string $configuredPath): string
    {
        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        // Use Laravel's base_path if available, otherwise use current directory
        $basePath = function_exists('base_path') ? base_path() : getcwd();
        return $basePath . DIRECTORY_SEPARATOR . $configuredPath;
    }

    /**
     * Checks if a path is absolute.
     * Determines whether a path is absolute or relative
     * for proper path resolution.
     *
     * Parameters:
     *   - string $path: The path to check.
     *
     * Returns:
     *   - bool: True if the path is absolute, false otherwise.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path);
    }

    /**
     * Gets the full file path for attribute storage.
     * Returns the complete file path where attribute data
     * should be stored based on storage type and configuration.
     *
     * Returns:
     *   - string: The complete storage file path.
     */
    private function getStorageFilePath(): string
    {
        $filename = match ($this->storageType) {
            AttributeStorageTypeEnum::JSON_FILE => AttributeConstants::DEFAULT_JSON_FILENAME,
            AttributeStorageTypeEnum::PHP_FILE => AttributeConstants::DEFAULT_PHP_FILENAME,
            default => AttributeConstants::DEFAULT_JSON_FILENAME,
        };

        return $this->storagePath . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Ensures the storage directory exists.
     * Creates the storage directory if it doesn't exist
     * with appropriate permissions for file operations.
     */
    private function ensureStorageDirectory(): void
    {
        if (!File::isDirectory($this->storagePath)) {
            $this->createStorageDirectory();
        }
    }

    /**
     * Creates the storage directory with proper permissions.
     * Creates the directory structure needed for attribute storage
     * with appropriate permissions for security and access.
     *
     * Returns:
     *   - bool: True if directory creation was successful, false otherwise.
     */
    private function createStorageDirectory(): bool
    {
        try {
            $success = File::makeDirectory($this->storagePath, AttributeConstants::STORAGE_DIRECTORY_PERMISSIONS, true);

            if ($success) {
                $this->d("✅ [ATTRIBUTE-STORAGE] Created storage directory: {$this->storagePath}");
            } else {
                $this->d("❌ [ATTRIBUTE-STORAGE] Failed to create storage directory: {$this->storagePath}");
            }

            return $success;

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-STORAGE] Directory creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Writes attribute data to a file.
     * Persists attribute data in the appropriate format
     * based on the configured storage type.
     *
     * Parameters:
     *   - string $filePath: The file path to write to.
     *   - array<string, array<string, mixed>> $attributes: The attribute data to write.
     *
     * Returns:
     *   - bool: True if writing was successful, false otherwise.
     */
    private function writeAttributeFile(string $filePath, array $attributes): bool
    {
        try {
            $content = match ($this->storageType) {
                AttributeStorageTypeEnum::JSON_FILE => json_encode($attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                AttributeStorageTypeEnum::PHP_FILE => "<?php\n\nreturn " . var_export($attributes, true) . ";\n",
                default => json_encode($attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            };

            if ($content === false) {
                $this->d("❌ [ATTRIBUTE-STORAGE] Failed to encode attribute data");
                return false;
            }

            $success = File::put($filePath, $content);

            if (!$success) {
                $this->d("❌ [ATTRIBUTE-STORAGE] Failed to write file: {$filePath}");
                return false;
            }

            // Set file permissions
            File::chmod($filePath, AttributeConstants::STORAGE_FILE_PERMISSIONS);

            return true;

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-STORAGE] File write operation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reads attribute data from a file.
     * Loads attribute data from storage file and decodes
     * it based on the storage type format.
     *
     * Parameters:
     *   - string $filePath: The file path to read from.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: The loaded attribute data.
     */
    private function readAttributeFile(string $filePath): array
    {
        try {
            $content = File::get($filePath);

            if ($content === false) {
                $this->d("❌ [ATTRIBUTE-STORAGE] Failed to read file: {$filePath}");
                return [];
            }

            $attributes = match ($this->storageType) {
                AttributeStorageTypeEnum::JSON_FILE => json_decode($content, true),
                AttributeStorageTypeEnum::PHP_FILE => include $filePath,
                default => json_decode($content, true),
            };

            if (!is_array($attributes)) {
                $this->d("❌ [ATTRIBUTE-STORAGE] Invalid attribute data format in file: {$filePath}");
                return [];
            }

            return $attributes;

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-STORAGE] File read operation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Creates a backup of the storage file.
     * Creates a backup copy of the existing storage file
     * before performing write operations for data safety.
     *
     * Parameters:
     *   - string $filePath: The file path to backup.
     */
    private function createBackup(string $filePath): void
    {
        try {
            $backupPath = $filePath . AttributeConstants::BACKUP_FILE_SUFFIX . '.' . date('Y-m-d-H-i-s');

            if (File::copy($filePath, $backupPath)) {
                $this->d("💾 [ATTRIBUTE-STORAGE] Created backup: {$backupPath}");

                // Clean up old backups (keep only last 5)
                $this->cleanupOldBackups(File::dirname($filePath));
            } else {
                $this->d("⚠️ [ATTRIBUTE-STORAGE] Failed to create backup");
            }

        } catch (\Exception $e) {
            $this->d("⚠️ [ATTRIBUTE-STORAGE] Backup creation failed: " . $e->getMessage());
        }
    }

    /**
     * Cleans up old backup files.
     * Removes old backup files to prevent storage directory
     * from accumulating too many backup files.
     *
     * Parameters:
     *   - string $directory: The directory to clean up.
     */
    private function cleanupOldBackups(string $directory): void
    {
        try {
            $backupFiles = File::glob($directory . '/*' . AttributeConstants::BACKUP_FILE_SUFFIX . '*');

            if (count($backupFiles) > 5) {
                // Sort by modification time and remove oldest
                usort($backupFiles, fn($a, $b) => File::lastModified($a) - File::lastModified($b));

                $filesToRemove = array_slice($backupFiles, 0, -5);
                foreach ($filesToRemove as $file) {
                    File::delete($file);
                }

                $this->d("🧹 [ATTRIBUTE-STORAGE] Cleaned up " . count($filesToRemove) . " old backup files");
            }

        } catch (\Exception $e) {
            $this->d("⚠️ [ATTRIBUTE-STORAGE] Backup cleanup failed: " . $e->getMessage());
        }
    }

    /**
     * Acquires a lock for concurrent access protection.
     * Creates a lock file to prevent concurrent access during
     * storage operations for data integrity.
     *
     * Parameters:
     *   - string $lockFile: The lock file path.
     *
     * Returns:
     *   - resource|false: The lock file handle or false on failure.
     */
    private function acquireLock(string $lockFile)
    {
        try {
            // Clean up stale lock files
            if (File::exists($lockFile)) {
                $lockAge = time() - File::lastModified($lockFile);
                if ($lockAge > AttributeConstants::MAX_LOCK_FILE_AGE) {
                    File::delete($lockFile);
                    $this->d("🧹 [ATTRIBUTE-STORAGE] Removed stale lock file");
                }
            }

            $handle = fopen($lockFile, 'w');
            if ($handle && flock($handle, LOCK_EX | LOCK_NB)) {
                fwrite($handle, (string) getmypid());
                return $handle;
            }

            $this->d("⚠️ [ATTRIBUTE-STORAGE] Failed to acquire lock");
            return false;

        } catch (\Exception $e) {
            $this->d("⚠️ [ATTRIBUTE-STORAGE] Lock acquisition failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Releases a file lock.
     * Releases the file lock and removes the lock file
     * after storage operations are completed.
     *
     * Parameters:
     *   - resource|false $handle: The lock file handle.
     *   - string $lockFile: The lock file path.
     */
    private function releaseLock($handle, string $lockFile): void
    {
        try {
            if ($handle) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }

            if (File::exists($lockFile)) {
                File::delete($lockFile);
            }

        } catch (\Exception $e) {
            $this->d("⚠️ [ATTRIBUTE-STORAGE] Lock release failed: " . $e->getMessage());
        }
    }

    /**
     * Updates storage performance metrics.
     * Merges new metrics with existing storage metrics
     * for performance monitoring and reporting.
     *
     * Parameters:
     *   - array<string, mixed> $newMetrics: New metrics to merge.
     */
    private function updateStorageMetrics(array $newMetrics): void
    {
        $this->storageMetrics = array_merge($this->storageMetrics, $newMetrics);
    }

    /**
     * Debug output function - prints debug information if debug mode is enabled.
     * Provides debugging output during storage operations to help
     * identify issues and track the storage process.
     *
     * Parameters:
     *   - string $message: The debug message to output.
     */
    private function d(string $message): void
    {
        if ($this->configuration->isDebugModeEnabled()) {
            echo "[DEBUG] {$message}\n";
        }
    }
}
