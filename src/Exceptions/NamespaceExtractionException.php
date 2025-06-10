<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Exceptions;

use Exception;

/**
 * NamespaceExtractionException represents errors that occur during namespace extraction operations.
 * This exception is thrown when the system fails to parse PHP files, extract namespace information,
 * or process token arrays during the namespace discovery process.
 *
 * The exception provides specific details about parsing failures and file processing errors
 * to help identify and resolve issues in the namespace extraction system.
 */
class NamespaceExtractionException extends Exception
{
    /**
     * The file path where namespace extraction failed.
     * Contains the specific PHP file that was being processed
     * when the extraction error occurred.
     */
    private ?string $filePath;

    /**
     * The extracted tokens that caused the parsing error.
     * Contains the PHP token array that was being processed
     * when the exception occurred, useful for debugging.
     *
     * @var array<int, mixed>
     */
    private array $tokens;

    /**
     * The line number where the parsing error occurred.
     * Indicates the specific line in the source file
     * where the namespace extraction failed.
     */
    private ?int $errorLine;

    /**
     * Creates a new NamespaceExtractionException instance.
     * Initializes the exception with extraction-specific error details
     * including file path, tokens, and line number information.
     *
     * Parameters:
     *   - string $message: The error message describing the extraction failure.
     *   - int $code: Optional error code for categorizing the exception type.
     *   - Exception|null $previous: Optional previous exception for exception chaining.
     *   - string|null $filePath: The file path where extraction failed.
     *   - array<int, mixed> $tokens: The PHP tokens being processed when error occurred.
     *   - int|null $errorLine: The line number where the error occurred.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        ?string $filePath = null,
        array $tokens = [],
        ?int $errorLine = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->filePath = $filePath;
        $this->tokens = $tokens;
        $this->errorLine = $errorLine;
    }

    /**
     * Creates a NamespaceExtractionException for file reading errors.
     * Factory method that creates an exception specifically for cases
     * where a PHP file cannot be read or accessed for token parsing.
     *
     * Parameters:
     *   - string $filePath: The path to the file that cannot be read.
     *   - string|null $reason: Optional reason for the read failure.
     *
     * Returns:
     *   - static: A new NamespaceExtractionException instance for file read errors.
     */
    public static function fileNotReadable(string $filePath, ?string $reason = null): static
    {
        $message = "Cannot read file '{$filePath}' for namespace extraction";
        if ($reason !== null) {
            $message .= ": {$reason}";
        }

        return new static(
            message: $message,
            filePath: $filePath
        );
    }

    /**
     * Creates a NamespaceExtractionException for token parsing errors.
     * Factory method that creates an exception for errors that occur
     * during PHP token parsing and namespace identification.
     *
     * Parameters:
     *   - string $filePath: The file being parsed when the error occurred.
     *   - array<int, mixed> $tokens: The token array that caused the parsing error.
     *   - string $parseError: Description of the parsing error.
     *
     * Returns:
     *   - static: A new NamespaceExtractionException instance for parsing errors.
     */
    public static function tokenParsingFailed(string $filePath, array $tokens, string $parseError): static
    {
        return new static(
            message: "Failed to parse tokens for namespace extraction in '{$filePath}': {$parseError}",
            filePath: $filePath,
            tokens: $tokens
        );
    }

    /**
     * Creates a NamespaceExtractionException for invalid namespace format errors.
     * Factory method that creates an exception for cases where an extracted
     * namespace does not conform to valid PHP namespace syntax.
     *
     * Parameters:
     *   - string $namespace: The invalid namespace string that was extracted.
     *   - string $filePath: The file where the invalid namespace was found.
     *
     * Returns:
     *   - static: A new NamespaceExtractionException instance for format errors.
     */
    public static function invalidNamespaceFormat(string $namespace, string $filePath): static
    {
        return new static(
            message: "Invalid namespace format '{$namespace}' extracted from '{$filePath}'",
            filePath: $filePath
        );
    }

    /**
     * Gets the file path where namespace extraction failed.
     * Returns the specific PHP file that was being processed
     * when the extraction error occurred.
     *
     * Returns:
     *   - string|null: The file path or null if not applicable.
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Gets the PHP tokens that were being processed when the error occurred.
     * Returns the token array that can be useful for debugging
     * parsing issues and understanding the extraction failure.
     *
     * Returns:
     *   - array<int, mixed>: The PHP tokens array.
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Gets the line number where the parsing error occurred.
     * Returns the specific line in the source file where
     * the namespace extraction encountered an issue.
     *
     * Returns:
     *   - int|null: The line number or null if not determined.
     */
    public function getErrorLine(): ?int
    {
        return $this->errorLine;
    }

    /**
     * Converts the exception to an array representation.
     * Creates a structured array containing all extraction-specific
     * exception details for logging and debugging purposes.
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
            'extraction_file_path' => $this->filePath,
            'error_line' => $this->errorLine,
            'token_count' => count($this->tokens),
            'trace' => $this->getTraceAsString(),
        ];
    }
}