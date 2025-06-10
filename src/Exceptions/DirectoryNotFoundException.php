<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Exceptions;

use Exception;

/**
 * DirectoryNotFoundException represents errors when specified directories cannot be found.
 * This exception is thrown when the module discovery system attempts to access
 * directories that do not exist or are not accessible during scanning operations.
 *
 * The exception provides specific directory path information and suggested
 * resolutions to help users identify and fix directory-related configuration issues.
 */
class DirectoryNotFoundException extends Exception
{
    /**
     * The directory path that could not be found.
     * Contains the specific path that was requested but does not exist
     * or is not accessible to the discovery system.
     */
    private string $directoryPath;

    /**
     * Suggested alternative directories that might exist.
     * Contains an array of directory paths that could be valid
     * alternatives to the missing directory.
     *
     * @var array<string>
     */
    private array $suggestions;

    /**
     * The base path used for resolving relative directory paths.
     * Contains the root directory that was used as a reference
     * when attempting to locate the missing directory.
     */
    private ?string $basePath;

    /**
     * Creates a new DirectoryNotFoundException instance.
     * Initializes the exception with directory-specific error details
     * including the missing path, suggestions, and base path information.
     *
     * Parameters:
     *   - string $directoryPath: The directory path that could not be found.
     *   - string|null $basePath: The base path used for directory resolution.
     *   - array<string> $suggestions: Suggested alternative directory paths.
     *   - string|null $message: Optional custom error message.
     *   - int $code: Optional error code for categorizing the exception type.
     *   - Exception|null $previous: Optional previous exception for exception chaining.
     */
    public function __construct(
        string $directoryPath,
        ?string $basePath = null,
        array $suggestions = [],
        ?string $message = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        $this->directoryPath = $directoryPath;
        $this->basePath = $basePath;
        $this->suggestions = $suggestions;

        if ($message === null) {
            $message = $this->generateDefaultMessage();
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Creates a DirectoryNotFoundException for missing modules directory.
     * Factory method that creates an exception specifically for cases
     * where the default modules directory cannot be found.
     *
     * Parameters:
     *   - string $modulesPath: The modules directory path that is missing.
     *   - string|null $basePath: The application base path for reference.
     *
     * Returns:
     *   - static: A new DirectoryNotFoundException instance for missing modules directory.
     */
    public static function modulesDirectoryMissing(string $modulesPath, ?string $basePath = null): static
    {
        $suggestions = [
            'app/Modules',
            'modules',
            'src/Modules',
            'packages',
        ];

        return new static(
            directoryPath: $modulesPath,
            basePath: $basePath,
            suggestions: $suggestions,
            message: "Modules directory '{$modulesPath}' does not exist. Please create the directory or configure an alternative path."
        );
    }

    /**
     * Creates a DirectoryNotFoundException for inaccessible directory.
     * Factory method that creates an exception for directories that exist
     * but cannot be accessed due to permission or other access restrictions.
     *
     * Parameters:
     *   - string $directoryPath: The directory path that cannot be accessed.
     *   - string $accessError: Description of the access restriction.
     *
     * Returns:
     *   - static: A new DirectoryNotFoundException instance for access errors.
     */
    public static function directoryNotAccessible(string $directoryPath, string $accessError): static
    {
        return new static(
            directoryPath: $directoryPath,
            message: "Directory '{$directoryPath}' is not accessible: {$accessError}"
        );
    }

    /**
     * Generates the default error message for the exception.
     * Creates a comprehensive error message that includes the missing
     * directory path, base path context, and available suggestions.
     *
     * Returns:
     *   - string: The generated default error message.
     */
    private function generateDefaultMessage(): string
    {
        $message = "Directory '{$this->directoryPath}' not found";

        if ($this->basePath !== null) {
            $message .= " (resolved from base path: '{$this->basePath}')";
        }

        if (!empty($this->suggestions)) {
            $suggestionsList = implode(', ', array_map(fn($path) => "'{$path}'", $this->suggestions));
            $message .= ". Suggested alternatives: {$suggestionsList}";
        }

        return $message;
    }

    /**
     * Gets the directory path that could not be found.
     * Returns the specific path that was requested but does not exist
     * or is not accessible to the discovery system.
     *
     * Returns:
     *   - string: The missing directory path.
     */
    public function getDirectoryPath(): string
    {
        return $this->directoryPath;
    }

    /**
     * Gets the base path used for directory resolution.
     * Returns the root directory that was used as a reference
     * when attempting to locate the missing directory.
     *
     * Returns:
     *   - string|null: The base path or null if not applicable.
     */
    public function getBasePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * Gets the suggested alternative directory paths.
     * Returns an array of directory paths that could be valid
     * alternatives to the missing directory.
     *
     * Returns:
     *   - array<string>: Array of suggested directory paths.
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Converts the exception to an array representation.
     * Creates a structured array containing all directory-specific
     * exception details for logging and API response purposes.
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
            'directory_path' => $this->directoryPath,
            'base_path' => $this->basePath,
            'suggestions' => $this->suggestions,
            'trace' => $this->getTraceAsString(),
        ];
    }
}