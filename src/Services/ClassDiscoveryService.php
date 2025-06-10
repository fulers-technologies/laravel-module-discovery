<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Constants\DirectoryConstants;
use LaravelModuleDiscovery\ComposerHook\Enums\DiscoveryStatusEnum;
use LaravelModuleDiscovery\ComposerHook\Enums\FileTypeEnum;
use LaravelModuleDiscovery\ComposerHook\Exceptions\DirectoryNotFoundException;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
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
     * extraction and path resolution operations.
     *
     * Parameters:
     *   - NamespaceExtractorInterface $namespaceExtractor: Service for extracting namespaces from PHP files.
     *   - PathResolverInterface $pathResolver: Service for resolving and normalizing file paths.
     */
    public function __construct(
        private readonly NamespaceExtractorInterface $namespaceExtractor,
        private readonly PathResolverInterface $pathResolver
    ) {
        $this->status = DiscoveryStatusEnum::INITIALIZED;
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
     *
     * Returns:
     *   - static: A new ClassDiscoveryService instance.
     */
    public static function make(
        ?NamespaceExtractorInterface $namespaceExtractor = null,
        ?PathResolverInterface $pathResolver = null
    ): static {
        return new static(
            $namespaceExtractor ?? NamespaceExtractorService::make(),
            $pathResolver ?? PathResolverService::make()
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
        $startTime = microtime(true);
        
        try {
            if (!$this->validateDirectory($directoryPath)) {
                throw DirectoryNotFoundException::modulesDirectoryMissing($directoryPath);
            }

            $discoveredClasses = [];
            $processedFiles = 0;
            $errorFiles = [];

            $iterator = new RecursiveDirectoryIterator(
                $directoryPath,
                RecursiveDirectoryIterator::SKIP_DOTS
            );

            foreach (new RecursiveIteratorIterator($iterator) as $file) {
                if (!$this->shouldProcessFile($file->getPathname())) {
                    continue;
                }

                try {
                    $namespace = $this->namespaceExtractor->extractNamespace($file->getPathname());
                    
                    if ($namespace !== null) {
                        $directoryPath = $this->pathResolver->getDirectoryPath($file->getPathname());
                        $discoveredClasses[$namespace] = $directoryPath;
                    }

                    $processedFiles++;
                } catch (\Exception $e) {
                    $errorFiles[] = [
                        'file' => $file->getPathname(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $this->discoveryStats = [
                'processed_files' => $processedFiles,
                'discovered_namespaces' => count($discoveredClasses),
                'processing_time' => microtime(true) - $startTime,
                'error_files' => $errorFiles,
                'directory_path' => $directoryPath,
            ];

            $this->status = DiscoveryStatusEnum::COMPLETED;
            
            return $discoveredClasses;

        } catch (\Exception $e) {
            $this->status = DiscoveryStatusEnum::FAILED;
            $this->discoveryStats['error'] = $e->getMessage();
            
            if ($e instanceof DirectoryNotFoundException || $e instanceof ModuleDiscoveryException) {
                throw $e;
            }
            
            throw ModuleDiscoveryException::scanningFailed($directoryPath, $e->getMessage());
        }
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
        return is_dir($directoryPath) && is_readable($directoryPath);
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
            'status' => $this->status->value,
            'status_description' => $this->status->getDescription(),
            'is_terminal' => $this->status->isTerminal(),
            'statistics' => $this->discoveryStats,
        ];
    }

    /**
     * Determines if a file should be processed during discovery.
     * Checks the file extension and type to determine if it contains
     * PHP classes that should be included in namespace extraction.
     *
     * Parameters:
     *   - string $filePath: The full path to the file to evaluate.
     *
     * Returns:
     *   - bool: True if the file should be processed, false otherwise.
     */
    private function shouldProcessFile(string $filePath): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileType = FileTypeEnum::fromExtension($extension);
        
        return $fileType !== null && $fileType->shouldProcess();
    }
}