<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Enums;

/**
 * AttributeTypeEnum defines the supported attribute types for discovery operations.
 * This enumeration provides type-safe attribute type identification that determines
 * which attributes should be processed during the attribute discovery scanning process.
 *
 * The enum ensures consistent attribute type handling and prevents the use of
 * magic strings for attribute type comparisons throughout the system.
 */
enum AttributeTypeEnum: string
{
    /**
     * Class-level attributes applied to class declarations.
     * These attributes provide metadata about the entire class
     * and its behavior or configuration.
     */
    case CLASS_ATTRIBUTE = 'class';

    /**
     * Method-level attributes applied to method declarations.
     * These attributes provide metadata about specific methods
     * including routing, validation, and behavior configuration.
     */
    case METHOD_ATTRIBUTE = 'method';

    /**
     * Property-level attributes applied to class properties.
     * These attributes provide metadata about class properties
     * including validation rules, serialization, and mapping.
     */
    case PROPERTY_ATTRIBUTE = 'property';

    /**
     * Parameter-level attributes applied to method parameters.
     * These attributes provide metadata about method parameters
     * including validation, injection, and transformation rules.
     */
    case PARAMETER_ATTRIBUTE = 'parameter';

    /**
     * Constant-level attributes applied to class constants.
     * These attributes provide metadata about class constants
     * including documentation and usage information.
     */
    case CONSTANT_ATTRIBUTE = 'constant';

    /**
     * Function-level attributes applied to global functions.
     * These attributes provide metadata about standalone functions
     * including routing, middleware, and behavior configuration.
     */
    case FUNCTION_ATTRIBUTE = 'function';

    /**
     * Gets the human-readable description for the attribute type.
     * Provides user-friendly text that explains the attribute type
     * for logging, documentation, and display purposes.
     *
     * Returns:
     *   - string: A descriptive message explaining the attribute type.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CLASS_ATTRIBUTE => 'Class-level attributes applied to class declarations',
            self::METHOD_ATTRIBUTE => 'Method-level attributes applied to method declarations',
            self::PROPERTY_ATTRIBUTE => 'Property-level attributes applied to class properties',
            self::PARAMETER_ATTRIBUTE => 'Parameter-level attributes applied to method parameters',
            self::CONSTANT_ATTRIBUTE => 'Constant-level attributes applied to class constants',
            self::FUNCTION_ATTRIBUTE => 'Function-level attributes applied to global functions',
        };
    }

    /**
     * Determines if the attribute type should be processed during discovery.
     * Returns true for attribute types that should be included in
     * the discovery and registration process.
     *
     * Returns:
     *   - bool: True if the attribute type should be processed, false otherwise.
     */
    public function shouldProcess(): bool
    {
        return match ($this) {
            self::CLASS_ATTRIBUTE,
            self::METHOD_ATTRIBUTE,
            self::PROPERTY_ATTRIBUTE => true,
            self::PARAMETER_ATTRIBUTE,
            self::CONSTANT_ATTRIBUTE,
            self::FUNCTION_ATTRIBUTE => false, // Can be enabled based on requirements
        };
    }

    /**
     * Gets the reflection method name for this attribute type.
     * Returns the appropriate reflection method name that should be used
     * to extract attributes of this type from PHP reflection objects.
     *
     * Returns:
     *   - string: The reflection method name for attribute extraction.
     */
    public function getReflectionMethod(): string
    {
        return match ($this) {
            self::CLASS_ATTRIBUTE => 'getAttributes',
            self::METHOD_ATTRIBUTE => 'getAttributes',
            self::PROPERTY_ATTRIBUTE => 'getAttributes',
            self::PARAMETER_ATTRIBUTE => 'getAttributes',
            self::CONSTANT_ATTRIBUTE => 'getAttributes',
            self::FUNCTION_ATTRIBUTE => 'getAttributes',
        };
    }

    /**
     * Creates an AttributeTypeEnum instance from a reflection type.
     * Analyzes the provided reflection object type and returns the appropriate
     * enum case, or null if the type is not supported.
     *
     * Parameters:
     *   - string $reflectionType: The reflection type to analyze.
     *
     * Returns:
     *   - AttributeTypeEnum|null: The matching enum case or null if not found.
     */
    public static function fromReflectionType(string $reflectionType): ?self
    {
        return match (strtolower($reflectionType)) {
            'reflectionclass' => self::CLASS_ATTRIBUTE,
            'reflectionmethod' => self::METHOD_ATTRIBUTE,
            'reflectionproperty' => self::PROPERTY_ATTRIBUTE,
            'reflectionparameter' => self::PARAMETER_ATTRIBUTE,
            'reflectionclassconstant' => self::CONSTANT_ATTRIBUTE,
            'reflectionfunction' => self::FUNCTION_ATTRIBUTE,
            default => null,
        };
    }
}
