<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Exceptions;

use Exception;

/**
 * ModuleDiscoveryException represents errors that occur during module discovery operations.
 * This exception is thrown when general discovery processes encounter errors that prevent
 * successful completion of class scanning, namespace extraction, or autoloader registration.
 *
 * The exception provides detailed error information to help diagnose and resolve
 * issues in the module discovery system while maintaining proper error handling practices.
 */
class ModuleDiscoveryException extends Exception
{
    /**
     * Additional context data related to the discovery error.
     * Contains supplementary information that can help diagnose
     * the specific conditions that led to the exception.
     *
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * The directory path where the discovery operation failed.
     * Provides the specific location that was being processed
     * when the error occurred for better error reporting.
     */
    private ?string $failedPath;

    /**
     * Creates a new ModuleDiscoveryException instance.
     * Initializes the exception with error details, context information,
     * and optional path data to provide comprehensive error reporting.
     *
     * Parameters:
     *   - string $message: The error message describing what went wrong.
     *   - int $code: Optional error code for categorizing the exception type.
     *   - Exception|null $previous: Optional previous exception for exception chaining.
     *   - array<string, mixed> $context: Additional context data for debugging.
     *   - string|null $failedPath: The path where the discovery operation failed.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $context = [],
        ?string $failedPath = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->failedPath = $failedPath;
    }

    /**
     * Creates a ModuleDiscoveryException for directory access errors.
     * Factory method that creates an exception specifically for cases
     * where a directory cannot be accessed or does not exist.
     *
     * Parameters:
     *   - string $directoryPath: The path to the inaccessible directory.
     *   - string|null $reason: Optional reason for the access failure.
     *
     * Returns:
     *   - static: A new ModuleDiscoveryException instance for directory access errors.
     */
    public static function directoryNotAccessible(string $directoryPath, ?string $reason = null): static
    {
        $message = "Directory '{$directoryPath}' is not accessible";
        if ($reason !== null) {
            $message .= ": {$reason}";
        }

        return new static(
            message: $message,
            context: ['directory_path' => $directoryPath, 'reason' => $reason],
            failedPath: $directoryPath
        );
    }

    /**
     * Creates a ModuleDiscoveryException for scanning operation failures.
     * Factory method that creates an exception for errors that occur
     * during the directory scanning and file enumeration process.
     *
     * Parameters:
     *   - string $directoryPath: The directory being scanned when the error occurred.
     *   - string $scanError: Description of the scanning error.
     *
     * Returns:
     *   - static: A new ModuleDiscoveryException instance for scanning errors.
     */
    public static function scanningFailed(string $directoryPath, string $scanError): static
    {
        return new static(
            message: "Failed to scan directory '{$directoryPath}': {$scanError}",
            context: ['directory_path' => $directoryPath, 'scan_error' => $scanError],
            failedPath: $directoryPath
        );
    }

    /**
     * Gets the additional context data associated with the exception.
     * Returns the context array containing supplementary information
     * that can help with debugging and error resolution.
     *
     * Returns:
     *   - array<string, mixed>: The context data array.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Gets the path where the discovery operation failed.
     * Returns the specific directory or file path that was being
     * processed when the exception occurred.
     *
     * Returns:
     *   - string|null: The failed path or null if not applicable.
     */
    public function getFailedPath(): ?string
    {
        return $this->failedPath;
    }

    /**
     * Converts the exception to an array representation.
     * Creates a structured array containing all exception details
     * for logging, debugging, or API response purposes.
     *
     * Returns:
     *   - array<string, mixed>: Array representation of the exception.
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'failed_path' => $this->failedPath,
            'trace' => $this->getTraceAsString(),
        ];
    }
}