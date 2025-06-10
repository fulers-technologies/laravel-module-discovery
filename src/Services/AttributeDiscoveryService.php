<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Constants\AttributeConstants;
use LaravelModuleDiscovery\ComposerHook\Enums\AttributeTypeEnum;
use LaravelModuleDiscovery\ComposerHook\Enums\DiscoveryStatusEnum;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use ReflectionClass;
use ReflectionException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * AttributeDiscoveryService implements comprehensive attribute discovery functionality.
 * This service handles the scanning of PHP classes to locate and extract attribute
 * information, providing detailed metadata about attributes and their usage.
 *
 * The service coordinates with class discovery and configuration services to provide
 * comprehensive attribute discovery capabilities with performance optimization.
 */
class AttributeDiscoveryService implements AttributeDiscoveryInterface
{
    /**
     * Current status of the attribute discovery operation.
     * Tracks the progress and state of the discovery process
     * using the DiscoveryStatusEnum values.
     */
    private DiscoveryStatusEnum $status;

    /**
     * Statistics and metadata from the last discovery operation.
     * Contains information about processed classes, found attributes,
     * processing time, and any errors encountered.
     *
     * @var array<string, mixed>
     */
    private array $discoveryStats;

    /**
     * Cache of discovered attributes to improve performance.
     * Stores previously discovered attribute information to avoid
     * re-processing the same classes during discovery operations.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $attributeCache;

    /**
     * Creates a new AttributeDiscoveryService instance.
     * Initializes the service with required dependencies for class discovery,
     * configuration management, and attribute processing.
     *
     * Parameters:
     *   - ClassDiscoveryInterface $classDiscovery: Service for discovering classes in directories.
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly ClassDiscoveryInterface $classDiscovery,
        private readonly ConfigurationInterface $configuration
    ) {
        $this->status = DiscoveryStatusEnum::INITIALIZED;
        $this->discoveryStats = [];
        $this->attributeCache = [];
    }

    /**
     * Creates a new AttributeDiscoveryService instance using static factory method.
     * Provides a convenient way to instantiate the service with default
     * dependencies without using the new keyword.
     *
     * Parameters:
     *   - ClassDiscoveryInterface|null $classDiscovery: Optional custom class discovery service.
     *   - ConfigurationInterface|null $configuration: Optional custom configuration service.
     *
     * Returns:
     *   - static: A new AttributeDiscoveryService instance.
     */
    public static function make(
        ?ClassDiscoveryInterface $classDiscovery = null,
        ?ConfigurationInterface $configuration = null
    ): static {
        return new static(
            $classDiscovery ?? ClassDiscoveryService::make(),
            $configuration ?? ConfigurationService::make()
        );
    }

    /**
     * Discovers attributes within the specified directory path.
     * Scans through the directory structure to identify PHP classes
     * and extract their attribute information for registration.
     *
     * Parameters:
     *   - string $directoryPath: The absolute path to the directory to scan for attributes.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: An associative array mapping classes to their attributes.
     *
     * @throws ModuleDiscoveryException When discovery operations fail.
     */
    public function discoverAttributes(string $directoryPath): array
    {
        $this->status = DiscoveryStatusEnum::IN_PROGRESS;
        $startTime = microtime(true);

        $this->d("🔍 [ATTRIBUTE-DISCOVERY] Starting attribute discovery in: {$directoryPath}");

        try {
            // First discover all classes in the directory
            $discoveredClasses = $this->classDiscovery->discoverClasses($directoryPath);

            if (empty($discoveredClasses)) {
                $this->d("⚠️ [ATTRIBUTE-DISCOVERY] No classes found for attribute discovery");
                $this->status = DiscoveryStatusEnum::COMPLETED;
                return [];
            }

            $this->d("📋 [ATTRIBUTE-DISCOVERY] Found " . count($discoveredClasses) . " namespaces to scan");

            $allAttributes = [];
            $processedClasses = 0;
            $errorClasses = [];
            $maxBatchSize = $this->configuration->get('attributes.max_batch_size', AttributeConstants::MAX_BATCH_SIZE);

            // Process classes in batches for memory efficiency
            $classFiles = $this->findClassFiles($directoryPath);
            $batches = array_chunk($classFiles, $maxBatchSize);

            $this->d("🔄 [ATTRIBUTE-DISCOVERY] Processing " . count($classFiles) . " classes in " . count($batches) . " batches");

            foreach ($batches as $batchIndex => $batch) {
                $this->d("📦 [ATTRIBUTE-DISCOVERY] Processing batch " . ($batchIndex + 1) . "/" . count($batches));

                foreach ($batch as $classFile) {
                    try {
                        $className = $this->extractClassNameFromFile($classFile);

                        if ($className && $this->shouldProcessClass($className)) {
                            $attributes = $this->extractClassAttributes($className);

                            if (!empty($attributes)) {
                                $allAttributes[$className] = $attributes;
                                $this->d("✅ [ATTRIBUTE-DISCOVERY] Found attributes in: {$className}");
                            }
                        }

                        $processedClasses++;

                    } catch (\Exception $e) {
                        $errorClasses[] = [
                            'file' => $classFile,
                            'error' => $e->getMessage(),
                        ];
                        $this->d("❌ [ATTRIBUTE-DISCOVERY] Error processing {$classFile}: " . $e->getMessage());
                    }
                }

                // Memory cleanup between batches
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            $this->discoveryStats = [
                'processed_classes' => $processedClasses,
                'discovered_attributes' => count($allAttributes),
                'processing_time' => microtime(true) - $startTime,
                'error_classes' => $errorClasses,
                'directory_path' => $directoryPath,
                'batch_count' => count($batches),
                'max_batch_size' => $maxBatchSize,
            ];

            $this->status = DiscoveryStatusEnum::COMPLETED;

            $this->d("🎉 [ATTRIBUTE-DISCOVERY] Discovery completed successfully");
            $this->d("📊 [ATTRIBUTE-DISCOVERY] Stats: {$processedClasses} classes, " . count($allAttributes) . " with attributes");

            return $allAttributes;

        } catch (\Exception $e) {
            $this->status = DiscoveryStatusEnum::FAILED;
            $this->discoveryStats['error'] = $e->getMessage();
            $this->discoveryStats['processing_time'] = microtime(true) - $startTime;

            $this->d("💥 [ATTRIBUTE-DISCOVERY] Discovery failed: " . $e->getMessage());

            throw ModuleDiscoveryException::scanningFailed($directoryPath, $e->getMessage());
        }
    }

    /**
     * Extracts attributes from a specific PHP class.
     * Analyzes a single class to identify and extract all attribute
     * information including parameters and metadata.
     *
     * Parameters:
     *   - string $className: The fully qualified class name to analyze.
     *
     * Returns:
     *   - array<string, mixed>: Array of attribute information for the class.
     */
    public function extractClassAttributes(string $className): array
    {
        $this->d("🔍 [ATTRIBUTE-EXTRACTOR] Extracting attributes from: {$className}");

        // Check cache first
        if (isset($this->attributeCache[$className])) {
            $this->d("💾 [ATTRIBUTE-EXTRACTOR] Found in cache: {$className}");
            return $this->attributeCache[$className];
        }

        try {
            $reflection = new ReflectionClass($className);
            $attributes = [];

            // Extract class-level attributes
            $classAttributes = $this->extractAttributesFromReflection($reflection, AttributeTypeEnum::CLASS_ATTRIBUTE);
            if (!empty($classAttributes)) {
                $attributes['class'] = $classAttributes;
            }

            // Extract method attributes
            $methodAttributes = [];
            foreach ($reflection->getMethods() as $method) {
                $methodAttrs = $this->extractAttributesFromReflection($method, AttributeTypeEnum::METHOD_ATTRIBUTE);
                if (!empty($methodAttrs)) {
                    $methodAttributes[$method->getName()] = $methodAttrs;
                }
            }
            if (!empty($methodAttributes)) {
                $attributes['methods'] = $methodAttributes;
            }

            // Extract property attributes
            $propertyAttributes = [];
            foreach ($reflection->getProperties() as $property) {
                $propAttrs = $this->extractAttributesFromReflection($property, AttributeTypeEnum::PROPERTY_ATTRIBUTE);
                if (!empty($propAttrs)) {
                    $propertyAttributes[$property->getName()] = $propAttrs;
                }
            }
            if (!empty($propertyAttributes)) {
                $attributes['properties'] = $propertyAttributes;
            }

            // Cache the result
            $this->attributeCache[$className] = $attributes;

            $this->d("📋 [ATTRIBUTE-EXTRACTOR] Extracted " . count($attributes) . " attribute types from: {$className}");

            return $attributes;

        } catch (ReflectionException $e) {
            $this->d("❌ [ATTRIBUTE-EXTRACTOR] Reflection failed for {$className}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validates discovered attributes against configuration rules.
     * Ensures attributes meet validation criteria and filtering
     * requirements before registration.
     *
     * Parameters:
     *   - array<string, array<string, mixed>> $attributes: The discovered attributes to validate.
     *
     * Returns:
     *   - array<string, array<string, mixed>>: Validated and filtered attributes.
     */
    public function validateAttributes(array $attributes): array
    {
        $this->d("🔍 [ATTRIBUTE-VALIDATOR] Validating " . count($attributes) . " classes with attributes");

        $validatedAttributes = [];
        $excludedClasses = $this->configuration->get('attributes.excluded_classes', []);
        $excludedAttributes = $this->configuration->get('attributes.excluded_attribute_types', AttributeConstants::EXCLUDED_ATTRIBUTE_CLASSES);

        foreach ($attributes as $className => $classAttributes) {
            // Skip excluded classes
            if (in_array($className, $excludedClasses, true)) {
                $this->d("⏭️ [ATTRIBUTE-VALIDATOR] Skipping excluded class: {$className}");
                continue;
            }

            $filteredAttributes = $this->filterAttributes($classAttributes, $excludedAttributes);

            if (!empty($filteredAttributes)) {
                $validatedAttributes[$className] = $filteredAttributes;
                $this->d("✅ [ATTRIBUTE-VALIDATOR] Validated attributes for: {$className}");
            }
        }

        $this->d("📊 [ATTRIBUTE-VALIDATOR] Validation completed: " . count($validatedAttributes) . "/" . count($attributes) . " classes passed");

        return $validatedAttributes;
    }

    /**
     * Retrieves the current attribute discovery status and statistics.
     * Returns information about the last discovery operation including
     * the number of attributes found, processing time, and any errors encountered.
     *
     * Returns:
     *   - array<string, mixed>: An array containing discovery status information.
     */
    public function getDiscoveryStatus(): array
    {
        return [
            'status' => $this->status->value,
            'status_description' => $this->status->getDescription(),
            'is_terminal' => $this->status->isTerminal(),
            'statistics' => $this->discoveryStats,
            'cache_size' => count($this->attributeCache),
        ];
    }

    /**
     * Extracts attributes from a reflection object.
     * Uses PHP reflection to extract attribute information from
     * classes, methods, properties, and other reflection targets.
     *
     * Parameters:
     *   - \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection: The reflection object.
     *   - AttributeTypeEnum $type: The type of attributes being extracted.
     *
     * Returns:
     *   - array<string, mixed>: Array of extracted attribute information.
     */
    private function extractAttributesFromReflection($reflection, AttributeTypeEnum $type): array
    {
        $attributes = [];

        try {
            $reflectionAttributes = $reflection->getAttributes();

            foreach ($reflectionAttributes as $attribute) {
                $attributeName = $attribute->getName();

                // Skip excluded attributes
                if (in_array($attributeName, AttributeConstants::EXCLUDED_ATTRIBUTE_CLASSES, true)) {
                    continue;
                }

                $attributeData = [
                    'name' => $attributeName,
                    'type' => $type->value,
                    'target' => $reflection->getName(),
                    'arguments' => [],
                    'metadata' => [],
                ];

                // Extract attribute arguments
                try {
                    $instance = $attribute->newInstance();
                    $attributeData['arguments'] = $this->extractAttributeArguments($attribute);
                    $attributeData['metadata'] = $this->extractAttributeMetadata($instance, $reflection);
                } catch (\Exception $e) {
                    $this->d("⚠️ [ATTRIBUTE-EXTRACTOR] Failed to instantiate attribute {$attributeName}: " . $e->getMessage());
                    $attributeData['arguments'] = $attribute->getArguments();
                }

                $attributes[] = $attributeData;
            }

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-EXTRACTOR] Failed to extract attributes: " . $e->getMessage());
        }

        return $attributes;
    }

    /**
     * Extracts arguments from an attribute instance.
     * Analyzes attribute arguments to provide detailed information
     * about attribute configuration and parameters.
     *
     * Parameters:
     *   - \ReflectionAttribute $attribute: The reflection attribute instance.
     *
     * Returns:
     *   - array<string, mixed>: Array of attribute arguments.
     */
    private function extractAttributeArguments(\ReflectionAttribute $attribute): array
    {
        try {
            return $attribute->getArguments();
        } catch (\Exception $e) {
            $this->d("⚠️ [ATTRIBUTE-EXTRACTOR] Failed to extract arguments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extracts metadata from an attribute instance.
     * Gathers additional metadata about the attribute including
     * its properties, methods, and configuration options.
     *
     * Parameters:
     *   - object $attributeInstance: The instantiated attribute object.
     *   - \ReflectionClass|\ReflectionMethod|\ReflectionProperty $target: The target reflection object.
     *
     * Returns:
     *   - array<string, mixed>: Array of attribute metadata.
     */
    private function extractAttributeMetadata(object $attributeInstance, $target): array
    {
        $metadata = [
            'class' => get_class($attributeInstance),
            'target_type' => get_class($target),
            'target_name' => $target->getName(),
        ];

        // Add target-specific metadata
        if ($target instanceof ReflectionClass) {
            $metadata['target_file'] = $target->getFileName();
            $metadata['target_namespace'] = $target->getNamespaceName();
        } elseif ($target instanceof \ReflectionMethod) {
            $metadata['target_class'] = $target->getDeclaringClass()->getName();
            $metadata['target_visibility'] = $this->getMethodVisibility($target);
        } elseif ($target instanceof \ReflectionProperty) {
            $metadata['target_class'] = $target->getDeclaringClass()->getName();
            $metadata['target_visibility'] = $this->getPropertyVisibility($target);
        }

        return $metadata;
    }

    /**
     * Gets the visibility of a method.
     * Determines the visibility level (public, protected, private)
     * of a reflection method for metadata purposes.
     *
     * Parameters:
     *   - \ReflectionMethod $method: The reflection method.
     *
     * Returns:
     *   - string: The visibility level.
     */
    private function getMethodVisibility(\ReflectionMethod $method): string
    {
        if ($method->isPublic()) {
            return 'public';
        } elseif ($method->isProtected()) {
            return 'protected';
        } elseif ($method->isPrivate()) {
            return 'private';
        }

        return 'unknown';
    }

    /**
     * Gets the visibility of a property.
     * Determines the visibility level (public, protected, private)
     * of a reflection property for metadata purposes.
     *
     * Parameters:
     *   - \ReflectionProperty $property: The reflection property.
     *
     * Returns:
     *   - string: The visibility level.
     */
    private function getPropertyVisibility(\ReflectionProperty $property): string
    {
        if ($property->isPublic()) {
            return 'public';
        } elseif ($property->isProtected()) {
            return 'protected';
        } elseif ($property->isPrivate()) {
            return 'private';
        }

        return 'unknown';
    }

    /**
     * Finds all PHP class files in a directory.
     * Recursively scans a directory to locate all PHP files
     * that potentially contain class definitions.
     *
     * Parameters:
     *   - string $directoryPath: The directory to scan.
     *
     * Returns:
     *   - array<string>: Array of PHP file paths.
     */
    private function findClassFiles(string $directoryPath): array
    {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-DISCOVERY] Failed to scan directory {$directoryPath}: " . $e->getMessage());
        }

        return $files;
    }

    /**
     * Extracts the class name from a PHP file.
     * Analyzes a PHP file to determine the fully qualified
     * class name defined within the file.
     *
     * Parameters:
     *   - string $filePath: The path to the PHP file.
     *
     * Returns:
     *   - string|null: The class name or null if not found.
     */
    private function extractClassNameFromFile(string $filePath): ?string
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }

            // Extract namespace
            $namespace = '';
            if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                $namespace = trim($matches[1]) . '\\';
            }

            // Extract class name
            if (preg_match('/(?:class|interface|trait)\s+(\w+)/', $content, $matches)) {
                return $namespace . $matches[1];
            }

        } catch (\Exception $e) {
            $this->d("❌ [ATTRIBUTE-DISCOVERY] Failed to extract class name from {$filePath}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Determines if a class should be processed for attributes.
     * Checks configuration and class characteristics to determine
     * if the class should be included in attribute discovery.
     *
     * Parameters:
     *   - string $className: The class name to check.
     *
     * Returns:
     *   - bool: True if the class should be processed, false otherwise.
     */
    private function shouldProcessClass(string $className): bool
    {
        // Skip excluded classes
        $excludedClasses = $this->configuration->get('attributes.excluded_classes', []);
        if (in_array($className, $excludedClasses, true)) {
            return false;
        }

        // Skip excluded namespaces
        $excludedNamespaces = $this->configuration->get('attributes.excluded_namespaces', []);
        foreach ($excludedNamespaces as $excludedNamespace) {
            if (str_starts_with($className, $excludedNamespace)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filters attributes based on exclusion rules.
     * Removes attributes that match exclusion criteria
     * from the discovered attribute set.
     *
     * Parameters:
     *   - array<string, mixed> $attributes: The attributes to filter.
     *   - array<string> $excludedAttributes: List of excluded attribute types.
     *
     * Returns:
     *   - array<string, mixed>: Filtered attributes.
     */
    private function filterAttributes(array $attributes, array $excludedAttributes): array
    {
        $filtered = [];

        foreach ($attributes as $type => $typeAttributes) {
            $filteredTypeAttributes = [];

            if (is_array($typeAttributes)) {
                foreach ($typeAttributes as $key => $attribute) {
                    if (is_array($attribute) && isset($attribute['name'])) {
                        if (!in_array($attribute['name'], $excludedAttributes, true)) {
                            $filteredTypeAttributes[$key] = $attribute;
                        }
                    } else {
                        $filteredTypeAttributes[$key] = $attribute;
                    }
                }
            }

            if (!empty($filteredTypeAttributes)) {
                $filtered[$type] = $filteredTypeAttributes;
            }
        }

        return $filtered;
    }

    /**
     * Debug output function - prints debug information if debug mode is enabled.
     * Provides debugging output during attribute discovery operations to help
     * identify issues and track the discovery process.
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
