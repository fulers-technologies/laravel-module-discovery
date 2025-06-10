<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Constants;

/**
 * TokenConstants defines all PHP token-related constant values used during
 * namespace extraction and file parsing operations. This class centralizes
 * token type references to prevent hardcoding and ensure consistent token
 * handling across the namespace extraction system.
 *
 * The constants include PHP token types, parsing patterns, and syntax elements
 * that are essential for accurate namespace identification within PHP files.
 */
final class TokenConstants
{
    /**
     * PHP token type for namespace declarations.
     * Represents the T_NAMESPACE token used to identify the beginning
     * of namespace declarations within PHP source code.
     */
    public const NAMESPACE_TOKEN = T_NAMESPACE;

    /**
     * PHP token type for whitespace characters.
     * Used to skip whitespace tokens during namespace parsing operations
     * and ensure accurate extraction of namespace names.
     */
    public const WHITESPACE_TOKEN = T_WHITESPACE;

    /**
     * PHP token type for string literals.
     * Represents T_STRING tokens that typically contain namespace
     * components and class names within PHP source code.
     */
    public const STRING_TOKEN = T_STRING;

    /**
     * PHP token type for namespace separator (backslash).
     * Represents the T_NS_SEPARATOR token used to separate namespace
     * components in fully qualified namespace declarations.
     */
    public const NAMESPACE_SEPARATOR_TOKEN = T_NS_SEPARATOR;

    /**
     * Statement terminator character for PHP statements.
     * Semicolon character that marks the end of namespace declarations
     * and other PHP statements during token parsing.
     */
    public const STATEMENT_TERMINATOR = ';';

    /**
     * Opening brace character for namespace blocks.
     * Left curly brace that indicates the beginning of a namespace
     * block when using bracketed namespace syntax.
     */
    public const NAMESPACE_BLOCK_START = '{';

    /**
     * PHP opening tag pattern for file validation.
     * Pattern used to identify valid PHP files that should be processed
     * for namespace extraction and class discovery.
     */
    public const PHP_OPEN_TAG_PATTERN = '/^<\?php/';

    /**
     * Maximum number of tokens to examine for namespace detection.
     * Limits the parsing depth to improve performance and prevent
     * excessive processing of large PHP files.
     */
    public const MAX_TOKENS_TO_EXAMINE = 100;

    /**
     * Default maximum tokens to examine from configuration.
     * Fallback value when configuration is not available or invalid.
     */
    public const DEFAULT_MAX_TOKENS_TO_EXAMINE = 100;

    /**
     * Minimum number of tokens required for valid namespace detection.
     * The minimum number of tokens that must be present to attempt
     * namespace extraction from a PHP file.
     */
    public const MIN_TOKENS_FOR_NAMESPACE = 3;

    /**
     * Token buffer size for efficient parsing operations.
     * The number of tokens to process in each parsing iteration
     * to balance memory usage and performance.
     */
    public const TOKEN_BUFFER_SIZE = 50;
}
