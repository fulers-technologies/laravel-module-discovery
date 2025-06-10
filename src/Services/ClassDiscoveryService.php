<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Enums\DiscoveryStatusEnum;
use LaravelModuleDiscovery\ComposerHook\Enums\FileTypeEnum;
use LaravelModuleDiscovery\ComposerHook\Exceptions\DirectoryNotFoundException;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\NamespaceExtractorInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\PathResolverInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ClassDiscoveryService implements the core class discovery functionality.
 * This service handles the scanning of directories to locate PHP files,
 * extract namespace information, and prepare data for autoloader registration.
 *
 * The service coordinates with namespace extraction and path resolution
 * services to provide comprehensive class discovery capabilities.
 */
class ClassDiscoveryService implements ClassDiscoveryInterface
{
    /**
     * Current status of the discovery operation.
     * Tracks the progress and state of the discovery process
     * using the DiscoveryStatusEnum values.
     */
    private DiscoveryStatusEnum $status;

    /**
     * Statistics and metadata from the last discovery operation.
     * Contains information about processed files, found namespaces,
     * processing time, and any errors encountered.
     *
     * @var array<string, mixed>
     */
    private array $discoveryStats;

    /**
     * Creates a new ClassDiscoveryService instance.
     * Initializes the service with required dependencies for namespace
     * extraction, path resolution, and configuration management.
     *
     * Parameters:
     *   - NamespaceExtractorInterface $namespaceExtractor: Service for extracting namespaces from PHP files.
     *   - PathResolverInterface $pathResolver: Service for resolving and normalizing file paths.
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly NamespaceExtractorInterface $namespaceExtractor,
        private readonly PathResolverInterface $pathResolver,
        private readonly ConfigurationInterface $configuration
    ) {
        $this->status         = DiscoveryStatusEnum::INITIALIZED;
        $this->discoveryStats = [];
    }

    /**
     * Creates a new ClassDiscoveryService instance using static factory method.
     * Provides a convenient way to instantiate the service with default
     * dependencies without using the new keyword.
     *
     * Parameters:
     *   - NamespaceExtractorInterface|null $namespaceExtractor: Optional custom namespace extractor.
     *   - PathResolverInterface|null $pathResolver: Optional custom path resolver.
     *   - ConfigurationInterface|null $configuration: Optional custom configuration service.
     *
     * Returns:
     *   - static: A new ClassDiscoveryService instance.
     */
    public static function make(
        ?NamespaceExtractorInterface $namespaceExtractor = null,
        ?PathResolverInterface $pathResolver = null,
        ?ConfigurationInterface $configuration = null
    ): static {
        return new static(
            $namespaceExtractor ?? NamespaceExtractorService::make(),
            $pathResolver ?? PathResolverService::make(),
            $configuration ?? ConfigurationService::make()
        );
    }

    /**
     * Discovers classes within the specified directory path.
     * Scans through the directory structure to identify PHP classes
     * and extract their namespace information for autoloading registration.
     *
     * Parameters:
     *   - string $directoryPath: The absolute path to the directory to scan for classes.
     *
     * Returns:
     *   - array<string, string>: An associative array mapping namespaces to their corresponding paths.
     *
     * @throws DirectoryNotFoundException When the specified directory does not exist.
     * @throws ModuleDiscoveryException When scanning operations fail.
     */
    public function discoverClasses(string $directoryPath): array
    {
        $this->status = DiscoveryStatusEnum::IN_PROGRESS;
        $startTime    = microtime(true);

        // Debug: Show what directory we're scanning
        $this->d("🔍 Starting discovery in directory: {$directoryPath}");
        $this->d("📁 Directory exists: " . (is_dir($directoryPath) ? 'YES' : 'NO'));
        $this->d("📖 Directory readable: " . (is_readable($directoryPath) ? 'YES' : 'NO'));

        try {
            if (! $this->validateDirectory($directoryPath)) {
                $this->d("❌ Directory validation failed for: {$directoryPath}");
                throw DirectoryNotFoundException::modulesDirectoryMissing(
                    $directoryPath,
                    null,
                    $this->configuration->getSuggestedDirectories()
                );
            }

            $discoveredClasses = [];
            $processedFiles    = 0;
            $errorFiles        = [];
            $maxErrors         = $this->configuration->getMaxErrorsBeforeStop();
            $continueOnErrors  = $this->configuration->shouldContinueOnErrors();

            $this->d("⚙️ Configuration:");
            $this->d("   - Max errors: {$maxErrors}");
            $this->d("   - Continue on errors: " . ($continueOnErrors ? 'YES' : 'NO'));
            $this->d("   - Supported extensions: " . implode(', ', $this->configuration->getSupportedExtensions()));

            $iterator = new RecursiveDirectoryIterator(
                $directoryPath,
                RecursiveDirectoryIterator::SKIP_DOTS
            );

            $recursiveIterator = new RecursiveIteratorIterator(
                $iterator,
                RecursiveIteratorIterator::SELF_FIRST
            );

            // Set maximum depth if configured
            $maxDepth = $this->configuration->getMaxScanDepth();
            if ($maxDepth > 0) {
                $recursiveIterator->setMaxDepth($maxDepth);
                $this->d("📏 Max scan depth set to: {$maxDepth}");
            }

            $totalFiles   = 0;
            $skippedFiles = 0;

            foreach ($recursiveIterator as $file) {
                $totalFiles++;
                $filePath = $file->getPathname();

                $this->d("📄 Found file: {$filePath}");

                // Check if we should stop due to too many errors
                if (! $continueOnErrors && count($errorFiles) >= $maxErrors) {
                    $this->d("🛑 Stopping due to too many errors ({$maxErrors})");
                    break;
                }

                if (! $this->shouldProcessFile($filePath)) {
                    $skippedFiles++;
                    $this->d("⏭️ Skipping file: {$filePath}");
                    $this->d("   - Extension: " . pathinfo($filePath, PATHINFO_EXTENSION));
                    $this->d("   - Is hidden: " . ($this->isHiddenFile($filePath) ? 'YES' : 'NO'));
                    continue;
                }

                $this->d("✅ Processing file: {$filePath}");

                try {
                    $namespace = $this->namespaceExtractor->extractNamespace($filePath);

                    $this->d("🔍 Extracted namespace: " . ($namespace ?? 'NULL'));

                    if ($namespace !== null) {
                        $shouldInclude = $this->shouldIncludeNamespace($namespace);
                        $this->d("📋 Should include namespace '{$namespace}': " . ($shouldInclude ? 'YES' : 'NO'));

                        if ($shouldInclude) {
                            // Get the base namespace (e.g., App\Modules\Example from App\Modules\Example\Controllers)
                            $baseNamespace = $this->extractBaseNamespace($namespace);
                            $basePath      = $this->getBasePathForNamespace($filePath, $namespace, $baseNamespace);

                            $this->d("🎯 Base namespace: {$baseNamespace}");
                            $this->d("🎯 Base path: {$basePath}");

                            // Only add if we haven't seen this base namespace before
                            if (! isset($discoveredClasses[$baseNamespace])) {
                                $discoveredClasses[$baseNamespace] = $basePath;
                                $this->d("✅ Added base namespace: {$baseNamespace} => {$basePath}");
                            } else {
                                $this->d("⏭️ Base namespace already exists: {$baseNamespace}");
                            }
                        }
                    }

                    $processedFiles++;
                } catch (\Exception $e) {
                    $errorFiles[] = [
                        'file'  => $filePath,
                        'error' => $e->getMessage(),
                    ];

                    $this->d("❌ Error processing file {$filePath}: " . $e->getMessage());

                    if (! $continueOnErrors) {
                        throw $e;
                    }
                }
            }

            $this->d("📊 Discovery Summary:");
            $this->d("   - Total files found: {$totalFiles}");
            $this->d("   - Files processed: {$processedFiles}");
            $this->d("   - Files skipped: {$skippedFiles}");
            $this->d("   - Namespaces discovered: " . count($discoveredClasses));
            $this->d("   - Error files: " . count($errorFiles));

            if (! empty($discoveredClasses)) {
                $this->d("🎯 Discovered namespaces:");
                foreach ($discoveredClasses as $namespace => $path) {
                    $this->d("   - {$namespace} => {$path}");
                }
            }

            $this->discoveryStats = [
                'processed_files'       => $processedFiles,
                'discovered_namespaces' => count($discoveredClasses),
                'processing_time'       => microtime(true) - $startTime,
                'error_files'           => $errorFiles,
                'directory_path'        => $directoryPath,
                'max_scan_depth'        => $maxDepth,
                'continue_on_errors'    => $continueOnErrors,
                'total_files_found'     => $totalFiles,
                'files_skipped'         => $skippedFiles,
            ];

            $this->status = DiscoveryStatusEnum::COMPLETED;

            return $discoveredClasses;

        } catch (\Exception $e) {
            $this->status                            = DiscoveryStatusEnum::FAILED;
            $this->discoveryStats['error']           = $e->getMessage();
            $this->discoveryStats['processing_time'] = microtime(true) - $startTime;

            $this->d("💥 Discovery failed with error: " . $e->getMessage());

            if ($e instanceof DirectoryNotFoundException || $e instanceof ModuleDiscoveryException) {
                throw $e;
            }

            throw ModuleDiscoveryException::scanningFailed($directoryPath, $e->getMessage());
        }
    }

    /**
     * Extracts the base namespace from a full namespace.
     * Converts a full namespace like "App\Modules\Example\Controllers"
     * to the base module namespace "App\Modules\Example".
     *
     * Parameters:
     *   - string $fullNamespace: The complete namespace.
     *
     * Returns:
     *   - string: The base namespace for the module.
     */
    private function extractBaseNamespace(string $fullNamespace): string
    {
        // For App\Modules\Example\Controllers, we want App\Modules\Example
        $parts = explode('\\', $fullNamespace);

        // Find the "Modules" part and take up to the next part
        $moduleIndex = array_search('Modules', $parts);
        if ($moduleIndex !== false && isset($parts[$moduleIndex + 1])) {
            // Take up to the module name (e.g., App\Modules\Example)
            return implode('\\', array_slice($parts, 0, $moduleIndex + 2));
        }

        // Fallback: take first 3 parts if it looks like App\Modules\ModuleName
        if (count($parts) >= 3) {
            return implode('\\', array_slice($parts, 0, 3));
        }

        return $fullNamespace;
    }

    /**
     * Gets the base path for a namespace based on the file path.
     * Calculates the directory path that should be registered for the base namespace.
     *
     * Parameters:
     *   - string $filePath: The path to the file containing the namespace.
     *   - string $fullNamespace: The complete namespace found in the file.
     *   - string $baseNamespace: The base namespace to register.
     *
     * Returns:
     *   - string: The base path for the namespace.
     */
    private function getBasePathForNamespace(string $filePath, string $fullNamespace, string $baseNamespace): string
    {
        $fileDir = dirname($filePath);

        // Calculate how many levels to go up from the file to the base namespace
        $fullParts = explode('\\', $fullNamespace);
        $baseParts = explode('\\', $baseNamespace);

        $levelsUp = count($fullParts) - count($baseParts);

        // Go up the directory structure
        $basePath = $fileDir;
        for ($i = 0; $i < $levelsUp; $i++) {
            $basePath = dirname($basePath);
        }

        return $basePath;
    }

    /**
     * Validates whether the specified directory exists and is accessible.
     * Performs preliminary checks before attempting class discovery operations.
     *
     * Parameters:
     *   - string $directoryPath: The directory path to validate.
     *
     * Returns:
     *   - bool: True if the directory is valid and accessible, false otherwise.
     */
    public function validateDirectory(string $directoryPath): bool
    {
        $this->d("🔍 Validating directory: {$directoryPath}");

        if (! is_dir($directoryPath) || ! is_readable($directoryPath)) {
            $this->d("❌ Directory validation failed: not a directory or not readable");
            return false;
        }

        // Check if directory is in excluded list
        $excludedDirectories = $this->configuration->getExcludedDirectories();
        $directoryName       = basename($directoryPath);

        $this->d("📋 Excluded directories: " . implode(', ', $excludedDirectories));
        $this->d("📁 Current directory name: {$directoryName}");

        $isExcluded = in_array($directoryName, $excludedDirectories, true);

        if ($isExcluded) {
            $this->d("❌ Directory is in excluded list");
            return false;
        }

        $this->d("✅ Directory validation passed");
        return true;
    }

    /**
     * Retrieves the current discovery status and statistics.
     * Returns information about the last discovery operation including
     * the number of classes found, processing time, and any errors encountered.
     *
     * Returns:
     *   - array<string, mixed>: An array containing discovery status information.
     */
    public function getDiscoveryStatus(): array
    {
        return [
            'status'             => $this->status->value,
            'status_description' => $this->status->getDescription(),
            'is_terminal'        => $this->status->isTerminal(),
            'statistics'         => $this->discoveryStats,
        ];
    }

    /**
     * Determines if a file should be processed during discovery.
     * Checks the file extension, type, and configuration settings
     * to determine if it contains PHP classes for namespace extraction.
     *
     * Parameters:
     *   - string $filePath: The full path to the file to evaluate.
     *
     * Returns:
     *   - bool: True if the file should be processed, false otherwise.
     */
    private function shouldProcessFile(string $filePath): bool
    {
        // Check if hidden files should be skipped
        if ($this->configuration->shouldSkipHiddenFiles() && $this->isHiddenFile($filePath)) {
            return false;
        }

        // Check file extension against supported extensions
        $extension           = pathinfo($filePath, PATHINFO_EXTENSION);
        $supportedExtensions = $this->configuration->getSupportedExtensions();

        if (! in_array($extension, $supportedExtensions, true)) {
            return false;
        }

        // Use FileTypeEnum for additional validation
        $fileType = FileTypeEnum::fromExtension($extension);
        return $fileType !== null && $fileType->shouldProcess();
    }

    /**
     * Determines if a namespace should be included in the discovery results.
     * Checks the namespace against configuration rules including excluded
     * prefixes, length requirements, and validation settings.
     *
     * Parameters:
     *   - string $namespace: The namespace to evaluate for inclusion.
     *
     * Returns:
     *   - bool: True if the namespace should be included, false otherwise.
     */
    private function shouldIncludeNamespace(string $namespace): bool
    {
        // Check namespace length requirements
        $minLength = $this->configuration->getMinNamespaceLength();
        $maxLength = $this->configuration->getMaxNamespaceLength();

        $this->d("📏 Namespace length check for '{$namespace}':");
        $this->d("   - Length: " . strlen($namespace));
        $this->d("   - Min required: {$minLength}");
        $this->d("   - Max allowed: {$maxLength}");

        if (strlen($namespace) < $minLength || strlen($namespace) > $maxLength) {
            $this->d("❌ Namespace length validation failed");
            return false;
        }

        // Check excluded namespace prefixes
        $excludedPrefixes = $this->configuration->getExcludedNamespacePrefixes();
        $this->d("🚫 Excluded prefixes: " . implode(', ', $excludedPrefixes));

        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($namespace, $prefix)) {
                $this->d("❌ Namespace matches excluded prefix: {$prefix}");
                return false;
            }
        }

        // Perform strict PSR-4 validation if enabled
        if ($this->configuration->isStrictPsr4ValidationEnabled()) {
            $isValid = $this->namespaceExtractor->validateNamespace($namespace);
            $this->d("🔍 PSR-4 validation for '{$namespace}': " . ($isValid ? 'PASSED' : 'FAILED'));
            return $isValid;
        }

        $this->d("✅ Namespace validation passed");
        return true;
    }

    /**
     * Checks if a file is hidden based on its name.
     * Determines whether a file or directory should be considered hidden
     * based on naming conventions (starting with dot).
     *
     * Parameters:
     *   - string $filePath: The file path to check.
     *
     * Returns:
     *   - bool: True if the file is hidden, false otherwise.
     */
    private function isHiddenFile(string $filePath): bool
    {
        $fileName = basename($filePath);
        return str_starts_with($fileName, '.');
    }

    /**
     * Debug output function - prints debug information if debug mode is enabled.
     * Provides debugging output during discovery operations to help
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

    /**
     * Debug dump function - dumps variable and exits if debug mode is enabled.
     * Provides detailed variable inspection during discovery operations
     * for debugging complex issues.
     *
     * Parameters:
     *   - mixed $data: The data to dump and inspect.
     */
    private function dd(mixed $data): void
    {
        if ($this->configuration->isDebugModeEnabled()) {
            echo "[DEBUG DUMP]\n";
            var_dump($data);
            exit(1);
        }
    }
}
