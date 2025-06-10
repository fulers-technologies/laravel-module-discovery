<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeRegistryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeStorageInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;

/**
 * AttributeRegistryService implements attribute registry management functionality.
 * This service handles the registration, storage, and retrieval of discovered
 * attributes, providing a centralized registry for attribute information.
 *
 * The service coordinates with storage backends to provide persistent and
 * efficient attribute registry operations with caching and performance optimization.
 */
class AttributeRegistryService implements AttributeRegistryInterface
{
    /**
     * In-memory attribute registry for fast access.
     * Stores attribute information in memory for quick retrieval
     * during the current request lifecycle.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $registry = [];

    /**
     * Registry statistics and metadata.
     * Contains information about registry operations including
     * registration counts, performance metrics, and error tracking.
     *
     * @var array<string, mixed>
     */
    private array $registryStats = [];

    /**
     * Creates a new AttributeRegistryService instance.
     * Initializes the service with required dependencies for storage
     * operations and configuration management.
     *
     * Parameters:
     *   - AttributeStorageInterface $storage: Service for attribute data persistence.
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly AttributeStorageInterface $storage,
        private readonly ConfigurationInterface $configuration
    ) {
        $this->initializeRegistry();
    }

    /**
     * Creates a new AttributeRegistryService instance using static factory method.
     * Provides a convenient way to instantiate the service with default
     * dependencies without using the new keyword.
     *
     * Parameters:
     *   - AttributeStorageInterface|null $storage: Optional custom storage service.
     *   - ConfigurationInterface|null $configuration: Optional custom configuration service.
     *
     * Returns:
     *   - static: A new AttributeRegistryService instance.
     */
    public static function make(
        ?AttributeStorageInterface $storage = null,
        ?ConfigurationInterface $configuration = null
    ): static {
        return new static(
            $storage ?? AttributeFileStorageService::make(),
            $configuration ?? ConfigurationService::make()
        );
    }

    /**
     * Registers discovered attributes in the registry.
     * Stores attribute information in the configured storage backend
     * for efficient retrieval and usage.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The attributes to register.
     *
     * Returns:
     *   - bool: True if registration was successful, false otherwise.
     */
    public function registerAttributes(array $attributes): bool
    {
        $this->d("🔧 [ATTRIBUTE-REGISTRY] Registering " . count($attributes) . " classes with attributes");

        try {
            $startTime = microtime(true);

            // Merge with existing registry
            $this->registry = array_merge($this->registry, $attributes);

            // Persist to storage
            $storageSuccess = $this->storage->store($this->registry);

            if ($storageSuccess) {
                $this->updateRegistryStats([
                    'last_registration_time' => time(),
                    'last_registration_count' => count($attributes),
                    'total_registered_classes' => count($this->registry),
                    'registration_duration' => microtime(true) - $startTime,
                ]);

                $this->d("✅ [ATTRIBUTE-REGISTRY] Successfully registered attributes for " . count($attributes) . " classes");
                return true;
            } else {
                $this->d("❌ [ATTRIBUTE-REGISTRY] Failed to persist attributes to storage");
                return false;
            }

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-REGISTRY] Registration failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves registered attributes by class name.
     * Returns attribute information for a specific class
     * from the registry storage.
     *
     * Parameters:
     *   - string $className: The class name to retrieve attributes for.
     *
     * Returns:
     *   - array<string, mixed>: Array of attributes for the specified class.
     */
    public function getAttributesByClass(string $className): array
    {
        $this->d("🔍 [ATTRIBUTE-REGISTRY] Retrieving attributes for class: {$className}");

        if (isset($this->registry[$className])) {
            $this->d("✅ [ATTRIBUTE-REGISTRY] Found attributes for: {$className}");
            return $this->registry[$className];
        }

        $this->d("⚠️ [ATTRIBUTE-REGISTRY] No attributes found for: {$className}");
        return [];
    }

    /**
     * Retrieves all registered attributes by attribute type.
     * Returns all classes that have a specific attribute type
     * registered in the system.
     *
     * Parameters:
     *   - string $attributeType: The attribute class name to search for.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: Array of classes with the specified attribute.
     */
    public function getAttributesByType(string $attributeType): array
    {
        $this->d("🔍 [ATTRIBUTE-REGISTRY] Searching for attribute type: {$attributeType}");

        $matchingClasses = [];

        foreach ($this->registry as $className => $classAttributes) {
            if ($this->classHasAttributeType($classAttributes, $attributeType)) {
                $matchingClasses[$className] = $classAttributes;
            }
        }

        $this->d("📊 [ATTRIBUTE-REGISTRY] Found " . count($matchingClasses) . " classes with attribute type: {$attributeType}");

        return $matchingClasses;
    }

    /**
     * Clears all registered attributes from the registry.
     * Removes all attribute information from storage
     * for fresh registration operations.
     *
     * Returns:
     *   - bool: True if clearing was successful, false otherwise.
     */
    public function clearRegistry(): bool
    {
        $this->d("🧹 [ATTRIBUTE-REGISTRY] Clearing registry");

        try {
            $this->registry = [];
            $storageSuccess = $this->storage->store([]);

            if ($storageSuccess) {
                $this->updateRegistryStats([
                    'last_clear_time' => time(),
                    'total_registered_classes' => 0,
                ]);

                $this->d("✅ [ATTRIBUTE-REGISTRY] Registry cleared successfully");
                return true;
            } else {
                $this->d("❌ [ATTRIBUTE-REGISTRY] Failed to clear storage");
                return false;
            }

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-REGISTRY] Clear operation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets registry statistics and metadata.
     * Returns information about the current state of the registry
     * including counts, storage size, and performance metrics.
     *
     * Returns:
     *   - array<string, mixed>: Array of registry statistics.
     */
    public function getRegistryStats(): array
    {
        $stats = array_merge($this->registryStats, [
            'total_classes' => count($this->registry),
            'total_attributes' => $this->countTotalAttributes(),
            'storage_type' => $this->storage->getStorageType(),
            'storage_available' => $this->storage->isAvailable(),
            'memory_usage' => memory_get_usage(true),
            'registry_size_bytes' => strlen(serialize($this->registry)),
        ]);

        // Add storage metrics if available
        try {
            $storageMetrics = $this->storage->getStorageMetrics();
            $stats['storage_metrics'] = $storageMetrics;
        } catch (\Exception $e) {
            $this->d("⚠️ [ATTRIBUTE-REGISTRY] Failed to get storage metrics: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Initializes the registry from storage.
     * Loads existing attribute data from the storage backend
     * to populate the in-memory registry.
     */
    private function initializeRegistry(): void
    {
        $this->d("🔧 [ATTRIBUTE-REGISTRY] Initializing registry from storage");

        try {
            if ($this->storage->isAvailable()) {
                $this->registry = $this->storage->retrieve();
                $this->d("✅ [ATTRIBUTE-REGISTRY] Loaded " . count($this->registry) . " classes from storage");
            } else {
                $this->d("⚠️ [ATTRIBUTE-REGISTRY] Storage not available, starting with empty registry");
                $this->registry = [];
            }

            $this->initializeRegistryStats();

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-REGISTRY] Failed to initialize from storage: " . $e->getMessage());
            $this->registry = [];
        }
    }

    /**
     * Initializes registry statistics.
     * Sets up initial statistics tracking for registry operations
     * and performance monitoring.
     */
    private function initializeRegistryStats(): void
    {
        $this->registryStats = [
            'initialized_at' => time(),
            'total_registered_classes' => count($this->registry),
            'last_registration_time' => null,
            'last_registration_count' => 0,
            'last_clear_time' => null,
            'registration_duration' => 0,
        ];
    }

    /**
     * Updates registry statistics.
     * Merges new statistics with existing registry statistics
     * for performance monitoring and reporting.
     *
     * Parameters:
     *   - array<string, mixed> $newStats: New statistics to merge.
     */
    private function updateRegistryStats(array $newStats): void
    {
        $this->registryStats = array_merge($this->registryStats, $newStats);
    }

    /**
     * Checks if a class has a specific attribute type.
     * Searches through class attributes to determine if the class
     * has an attribute of the specified type.
     *
     * Parameters:
     *   - array<string, mixed> $classAttributes: The class attributes to search.
     *   - string $attributeType: The attribute type to search for.
     *
     * Returns:
     *   - bool: True if the class has the attribute type, false otherwise.
     */
    private function classHasAttributeType(array $classAttributes, string $attributeType): bool
    {
        foreach ($classAttributes as $attributeCategory) {
            if (is_array($attributeCategory)) {
                foreach ($attributeCategory as $attribute) {
                    if (is_array($attribute) && isset($attribute['name'])) {
                        if ($attribute['name'] === $attributeType) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Counts the total number of attributes in the registry.
     * Calculates the total count of all attributes across all
     * registered classes for statistics purposes.
     *
     * Returns:
     *   - int: The total number of attributes.
     */
    private function countTotalAttributes(): int
    {
        $total = 0;

        foreach ($this->registry as $classAttributes) {
            foreach ($classAttributes as $attributeCategory) {
                if (is_array($attributeCategory)) {
                    $total += count($attributeCategory);
                }
            }
        }

        return $total;
    }

    /**
     * Debug output function - prints debug information if debug mode is enabled.
     * Provides debugging output during registry operations to help
     * identify issues and track the registration process.
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
