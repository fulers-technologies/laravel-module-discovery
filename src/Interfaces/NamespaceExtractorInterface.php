<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

/**
 * NamespaceExtractorInterface defines the contract for namespace extraction operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on namespace extraction functionality without mixing file operations
 * or other concerns.
 *
 * The interface provides methods to extract namespace information from PHP files
 * using token parsing and handle various namespace declaration formats.
 */
interface NamespaceExtractorInterface
{
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
     */
    public function extractNamespace(string $filePath): ?string;

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
    public function parseTokensForNamespace(array $tokens): ?string;

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
    public function validateNamespace(string $namespace): bool;
}