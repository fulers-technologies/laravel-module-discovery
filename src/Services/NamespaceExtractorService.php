<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Constants\TokenConstants;
use LaravelModuleDiscovery\ComposerHook\Exceptions\NamespaceExtractionException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\NamespaceExtractorInterface;

/**
 * NamespaceExtractorService implements namespace extraction from PHP files.
 * This service handles the parsing of PHP files using token analysis
 * to accurately extract namespace declarations for autoloader registration.
 *
 * The service uses PHP's token_get_all function to parse file contents
 * and identify namespace declarations while handling various syntax patterns.
 */
class NamespaceExtractorService implements NamespaceExtractorInterface
{
    /**
     * Cache of extracted namespaces to improve performance.
     * Stores previously extracted namespace information to avoid
     * re-parsing the same files during discovery operations.
     *
     * @var array<string, string|null>
     */
    private array $namespaceCache = [];

    /**
     * Creates a new NamespaceExtractorService instance using static factory method.
     * Provides a convenient way to instantiate the service without using the new keyword.
     *
     * Returns:
     *   - static: A new NamespaceExtractorService instance.
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Extracts the namespace from a PHP file using token parsing.
     * Analyzes the file content to identify and extract the namespace declaration
     * while handling various PHP syntax patterns and edge cases.
     *
     * Parameters:
     *   - string $filePath: The absolute path to the PHP file to analyze.
     *
     * Returns:
     *   - string|null: The extracted namespace string, or null if no namespace is found.
     *
     * @throws NamespaceExtractionException When file cannot be read or parsed.
     */
    public function extractNamespace(string $filePath): ?string
    {
        // Check cache first to avoid re-parsing
        if (isset($this->namespaceCache[$filePath])) {
            return $this->namespaceCache[$filePath];
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw NamespaceExtractionException::fileNotReadable($filePath, 'File does not exist or is not readable');
        }

        try {
            $fileContent = file_get_contents($filePath);
            
            if ($fileContent === false) {
                throw NamespaceExtractionException::fileNotReadable($filePath, 'Failed to read file contents');
            }

            // Quick check for PHP opening tag
            if (!preg_match(TokenConstants::PHP_OPEN_TAG_PATTERN, $fileContent)) {
                $this->namespaceCache[$filePath] = null;
                return null;
            }

            $tokens = token_get_all($fileContent);
            $namespace = $this->parseTokensForNamespace($tokens);

            if ($namespace !== null && !$this->validateNamespace($namespace)) {
                throw NamespaceExtractionException::invalidNamespaceFormat($namespace, $filePath);
            }

            $this->namespaceCache[$filePath] = $namespace;
            return $namespace;

        } catch (\Exception $e) {
            if ($e instanceof NamespaceExtractionException) {
                throw $e;
            }
            
            throw NamespaceExtractionException::tokenParsingFailed($filePath, [], $e->getMessage());
        }
    }

    /**
     * Parses PHP tokens to identify namespace declarations.
     * Processes the token array generated from PHP file content to locate
     * and extract namespace information with proper handling of syntax variations.
     *
     * Parameters:
     *   - array<int, mixed> $tokens: The array of PHP tokens to parse.
     *
     * Returns:
     *   - string|null: The extracted namespace string, or null if not found.
     */
    public function parseTokensForNamespace(array $tokens): ?string
    {
        $tokenCount = count($tokens);
        $maxTokensToCheck = min($tokenCount, TokenConstants::MAX_TOKENS_TO_EXAMINE);

        for ($i = 0; $i < $maxTokensToCheck; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== TokenConstants::NAMESPACE_TOKEN) {
                continue;
            }

            return $this->extractNamespaceFromTokenPosition($tokens, $i);
        }

        return null;
    }

    /**
     * Validates the extracted namespace format and structure.
     * Ensures the namespace follows PHP naming conventions and PSR-4 standards
     * before it can be used for autoloader registration.
     *
     * Parameters:
     *   - string $namespace: The namespace string to validate.
     *
     * Returns:
     *   - bool: True if the namespace is valid, false otherwise.
     */
    public function validateNamespace(string $namespace): bool
    {
        // Check for empty namespace
        if (trim($namespace) === '') {
            return false;
        }

        // Check for valid PHP namespace pattern
        $pattern = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*$/';
        
        return preg_match($pattern, $namespace) === 1;
    }

    /**
     * Extracts namespace from tokens starting at a specific position.
     * Processes tokens from the namespace declaration position to build
     * the complete namespace string including all components.
     *
     * Parameters:
     *   - array<int, mixed> $tokens: The complete token array.
     *   - int $startPosition: The position where the namespace token was found.
     *
     * Returns:
     *   - string|null: The extracted namespace string or null if extraction fails.
     */
    private function extractNamespaceFromTokenPosition(array $tokens, int $startPosition): ?string
    {
        $namespace = '';
        $i = $startPosition + 1;
        $tokenCount = count($tokens);

        // Skip whitespace after namespace keyword
        while ($i < $tokenCount && is_array($tokens[$i]) && $tokens[$i][0] === TokenConstants::WHITESPACE_TOKEN) {
            $i++;
        }

        // Extract namespace components
        while ($i < $tokenCount) {
            $token = $tokens[$i];

            // End of namespace declaration
            if ($token === TokenConstants::STATEMENT_TERMINATOR || $token === TokenConstants::NAMESPACE_BLOCK_START) {
                break;
            }

            // Add namespace components
            if (is_array($token)) {
                if (in_array($token[0], [TokenConstants::STRING_TOKEN, TokenConstants::NAMESPACE_SEPARATOR_TOKEN], true)) {
                    $namespace .= $token[1];
                }
            }

            $i++;
        }

        return $namespace !== '' ? trim($namespace) : null;
    }

    /**
     * Clears the namespace cache to free memory.
     * Removes all cached namespace extraction results to prevent
     * memory buildup during large discovery operations.
     */
    public function clearCache(): void
    {
        $this->namespaceCache = [];
    }

    /**
     * Gets the current cache statistics.
     * Returns information about cached namespace extractions
     * for performance monitoring and debugging purposes.
     *
     * Returns:
     *   - array<string, mixed>: Cache statistics including size and hit rate.
     */
    public function getCacheStats(): array
    {
        return [
            'cache_size' => count($this->namespaceCache),
            'cached_files' => array_keys($this->namespaceCache),
        ];
    }
}