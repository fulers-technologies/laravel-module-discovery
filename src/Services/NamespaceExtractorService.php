<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use LaravelModuleDiscovery\ComposerHook\Exceptions\NamespaceExtractionException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\NamespaceExtractorInterface;
use ReflectionClass;
use ReflectionException;

/**
 * NamespaceExtractorService implements namespace extraction from PHP files using Reflection.
 * This service handles the parsing of PHP files using PHP's Reflection API
 * to accurately extract namespace declarations for autoloader registration.
 *
 * The service uses PHP's built-in Reflection capabilities to analyze classes
 * and extract their namespace information reliably and efficiently.
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
     * Creates a new NamespaceExtractorService instance.
     * Initializes the service with configuration management for
     * controlling extraction behavior and performance settings.
     *
     * Parameters:
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly ConfigurationInterface $configuration
    ) {
    }

    /**
     * Creates a new NamespaceExtractorService instance using static factory method.
     * Provides a convenient way to instantiate the service without using the new keyword.
     *
     * Parameters:
     *   - ConfigurationInterface|null $configuration: Optional custom configuration service.
     *
     * Returns:
     *   - static: A new NamespaceExtractorService instance.
     */
    public static function make(?ConfigurationInterface $configuration = null): static
    {
        return new static(
            $configuration ?? ConfigurationService::make()
        );
    }

    /**
     * Extracts the namespace from a PHP file using Reflection API.
     * Analyzes the file content to identify and extract the namespace declaration
     * by loading the file and using PHP's Reflection capabilities.
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
        $this->d("🔍 [EXTRACTOR] Starting extraction for: {$filePath}");

        // Check cache first to avoid re-parsing (if caching is enabled)
        if ($this->configuration->isCachingEnabled() && isset($this->namespaceCache[$filePath])) {
            $this->d("💾 [EXTRACTOR] Found in cache: " . ($this->namespaceCache[$filePath] ?? 'NULL'));
            return $this->namespaceCache[$filePath];
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->d("❌ [EXTRACTOR] File not readable: {$filePath}");
            throw NamespaceExtractionException::fileNotReadable($filePath, 'File does not exist or is not readable');
        }

        try {
            $namespace = $this->extractNamespaceUsingReflection($filePath);

            if ($namespace !== null && !$this->validateNamespace($namespace)) {
                $this->d("❌ [EXTRACTOR] Namespace validation failed: {$namespace}");
                throw NamespaceExtractionException::invalidNamespaceFormat($namespace, $filePath);
            }

            // Cache the result if caching is enabled
            if ($this->configuration->isCachingEnabled()) {
                $this->manageCacheSize();
                $this->namespaceCache[$filePath] = $namespace;
                $this->d("💾 [EXTRACTOR] Cached result: " . ($namespace ?? 'NULL'));
            }

            $this->d("🎯 [EXTRACTOR] Final result: " . ($namespace ?? 'NULL'));
            return $namespace;

        } catch (\Exception $e) {
            $this->d("💥 [EXTRACTOR] Exception: " . $e->getMessage());

            if ($e instanceof NamespaceExtractionException) {
                throw $e;
            }

            throw NamespaceExtractionException::tokenParsingFailed($filePath, [], $e->getMessage());
        }
    }

    /**
     * Extracts namespace using PHP Reflection API.
     * Uses PHP's built-in Reflection capabilities to analyze the file
     * and extract namespace information from declared classes.
     *
     * Parameters:
     *   - string $filePath: The file path to analyze.
     *
     * Returns:
     *   - string|null: The extracted namespace or null if not found.
     */
    private function extractNamespaceUsingReflection(string $filePath): ?string
    {
        $this->d("🔍 [REFLECTION] Starting reflection analysis for: {$filePath}");

        // First, try to extract namespace using token parsing (faster for simple cases)
        $namespace = $this->extractNamespaceFromTokens($filePath);
        if ($namespace !== null) {
            $this->d("✅ [REFLECTION] Namespace found via tokens: {$namespace}");
            return $namespace;
        }

        // If token parsing fails, try to include the file and use reflection
        $this->d("🔄 [REFLECTION] Token parsing failed, trying file inclusion...");

        // Get declared classes before including the file
        $classesBefore = get_declared_classes();
        $interfacesBefore = get_declared_interfaces();
        $traitsBefore = get_declared_traits();

        // Temporarily suppress errors and include the file
        $errorReporting = error_reporting(0);

        try {
            // Use output buffering to capture any output from the included file
            ob_start();

            // Include the file (this will declare any classes/interfaces/traits)
            include_once $filePath;

            // Clean up any output
            ob_end_clean();

            // Get newly declared classes
            $classesAfter = get_declared_classes();
            $interfacesAfter = get_declared_interfaces();
            $traitsAfter = get_declared_traits();

            $newClasses = array_diff($classesAfter, $classesBefore);
            $newInterfaces = array_diff($interfacesAfter, $interfacesBefore);
            $newTraits = array_diff($traitsAfter, $traitsBefore);

            $this->d("🔍 [REFLECTION] Found " . count($newClasses) . " new classes, " .
                    count($newInterfaces) . " interfaces, " . count($newTraits) . " traits");

            // Try to get namespace from any newly declared class/interface/trait
            $allNewTypes = array_merge($newClasses, $newInterfaces, $newTraits);

            foreach ($allNewTypes as $typeName) {
                try {
                    $reflection = new ReflectionClass($typeName);
                    $namespace = $reflection->getNamespaceName();

                    if (!empty($namespace)) {
                        $this->d("✅ [REFLECTION] Found namespace via reflection: {$namespace}");
                        return $namespace;
                    }
                } catch (ReflectionException $e) {
                    $this->d("⚠️ [REFLECTION] Reflection failed for {$typeName}: " . $e->getMessage());
                    continue;
                }
            }

        } catch (\Throwable $e) {
            $this->d("❌ [REFLECTION] File inclusion failed: " . $e->getMessage());
        } finally {
            // Restore error reporting
            error_reporting($errorReporting);
        }

        $this->d("❌ [REFLECTION] No namespace found via reflection");
        return null;
    }

    /**
     * Extracts namespace from file using token parsing (fallback method).
     * Uses PHP's token_get_all function to parse the file and extract
     * namespace declarations without executing the code.
     *
     * Parameters:
     *   - string $filePath: The file path to analyze.
     *
     * Returns:
     *   - string|null: The extracted namespace or null if not found.
     */
    private function extractNamespaceFromTokens(string $filePath): ?string
    {
        $this->d("🔍 [TOKENS] Starting token analysis for: {$filePath}");

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $this->d("❌ [TOKENS] Failed to read file contents");
            return null;
        }

        $this->d("📄 [TOKENS] File content length: " . strlen($fileContent) . " bytes");

        // Quick check for PHP opening tag
        if (!preg_match('/^<\?php/', $fileContent)) {
            $this->d("❌ [TOKENS] No PHP opening tag found");
            return null;
        }

        $this->d("✅ [TOKENS] PHP opening tag found");

        try {
            $tokens = token_get_all($fileContent);
            $this->d("🔢 [TOKENS] Generated " . count($tokens) . " tokens");

            return $this->parseTokensForNamespace($tokens);

        } catch (\Throwable $e) {
            $this->d("❌ [TOKENS] Token parsing failed: " . $e->getMessage());
            return null;
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
        $maxTokensToCheck = min($tokenCount, $this->configuration->getMaxTokensToExamine());

        $this->d("🔍 [PARSER] Parsing {$tokenCount} tokens (checking first {$maxTokensToCheck})");

        for ($i = 0; $i < $maxTokensToCheck; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            $tokenType = $tokens[$i][0];

            if ($tokenType === T_NAMESPACE) {
                $this->d("🎉 [PARSER] Found T_NAMESPACE token at position {$i}");
                $namespace = $this->extractNamespaceFromTokenPosition($tokens, $i);
                $this->d("📝 [PARSER] Extracted namespace: " . ($namespace ?? 'NULL'));
                return $namespace;
            }
        }

        $this->d("❌ [PARSER] No T_NAMESPACE token found in first {$maxTokensToCheck} tokens");
        return null;
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

        $this->d("🔍 [EXTRACTOR] Starting namespace extraction from position {$startPosition}");

        // Skip whitespace after namespace keyword
        while ($i < $tokenCount && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
            $this->d("⏭️ [EXTRACTOR] Skipping whitespace at position {$i}");
            $i++;
        }

        // Extract namespace components
        while ($i < $tokenCount) {
            $token = $tokens[$i];

            $this->d("🔍 [EXTRACTOR] Processing token at position {$i}");

            // End of namespace declaration
            if ($token === ';' || $token === '{') {
                $this->d("🛑 [EXTRACTOR] Found namespace terminator: " . (is_string($token) ? $token : 'BLOCK_START'));
                break;
            }

            // Add namespace components
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenValue = $token[1];

                if (in_array($tokenType, [T_STRING, T_NS_SEPARATOR], true)) {
                    $namespace .= $tokenValue;
                    $this->d("➕ [EXTRACTOR] Added to namespace: '{$tokenValue}' (total: '{$namespace}')");
                } else {
                    $tokenName = token_name($tokenType);
                    $this->d("⏭️ [EXTRACTOR] Skipping token type {$tokenName}: '{$tokenValue}'");
                }
            } else {
                $this->d("⏭️ [EXTRACTOR] Skipping string token: '{$token}'");
            }

            $i++;
        }

        $result = $namespace !== '' ? trim($namespace) : null;
        $this->d("🎯 [EXTRACTOR] Final extracted namespace: " . ($result ?? 'NULL'));

        return $result;
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
        $this->d("🔍 [VALIDATOR] Validating namespace: '{$namespace}'");

        // Check for empty namespace
        if (trim($namespace) === '') {
            $this->d("❌ [VALIDATOR] Empty namespace");
            return false;
        }

        // Check length requirements from configuration
        $minLength = $this->configuration->getMinNamespaceLength();
        $maxLength = $this->configuration->getMaxNamespaceLength();

        $this->d("📏 [VALIDATOR] Length check: " . strlen($namespace) . " (min: {$minLength}, max: {$maxLength})");

        if (strlen($namespace) < $minLength || strlen($namespace) > $maxLength) {
            $this->d("❌ [VALIDATOR] Length validation failed");
            return false;
        }

        // Check for valid PHP namespace pattern
        $pattern = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*$/';

        $isValidPattern = preg_match($pattern, $namespace) === 1;
        $this->d("🔍 [VALIDATOR] Pattern validation: " . ($isValidPattern ? 'PASSED' : 'FAILED'));

        return $isValidPattern;
    }

    /**
     * Manages cache size to prevent memory issues.
     * Clears the cache when it exceeds the configured size limit
     * to maintain reasonable memory usage during discovery operations.
     */
    private function manageCacheSize(): void
    {
        $cacheLimit = $this->configuration->get('performance.cache_size_limit', 1000);

        if (count($this->namespaceCache) >= $cacheLimit) {
            // Remove oldest entries (simple FIFO approach)
            $this->namespaceCache = array_slice($this->namespaceCache, -($cacheLimit / 2), null, true);
        }
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
            'cache_enabled' => $this->configuration->isCachingEnabled(),
            'cache_limit' => $this->configuration->get('performance.cache_size_limit', 1000),
        ];
    }

    /**
     * Debug output function - prints debug information if debug mode is enabled.
     * Provides debugging output during namespace extraction operations to help
     * identify issues and track the extraction process.
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
