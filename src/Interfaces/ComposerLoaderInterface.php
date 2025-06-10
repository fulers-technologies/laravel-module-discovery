<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Interfaces;

use Composer\Autoload\ClassLoader;

/**
 * ComposerLoaderInterface defines the contract for Composer autoloader operations.
 * This interface follows the Interface Segregation Principle (ISP) by focusing
 * exclusively on Composer ClassLoader interactions without mixing discovery logic.
 *
 * The interface provides methods to register namespaces with Composer's autoloader,
 * manage PSR-4 mappings, and handle autoloader registration lifecycle.
 */
interface ComposerLoaderInterface
{
    /**
     * Retrieves the Composer ClassLoader instance.
     * Returns the active Composer autoloader instance that can be used
     * to register additional namespaces and PSR-4 mappings.
     *
     * Returns:
     *   - ClassLoader: The Composer ClassLoader instance.
     */
    public function getClassLoader(): ClassLoader;

    /**
     * Registers a PSR-4 namespace mapping with the Composer autoloader.
     * Adds a new namespace-to-directory mapping to enable automatic
     * class loading for the specified namespace prefix and path.
     *
     * Parameters:
     *   - string $namespace: The namespace prefix to register.
     *   - string $path: The directory path associated with the namespace.
     *
     * Returns:
     *   - bool: True if the registration was successful, false otherwise.
     */
    public function registerNamespace(string $namespace, string $path): bool;

    /**
     * Registers multiple namespace mappings in a batch operation.
     * Efficiently processes multiple namespace-to-path mappings
     * and registers them with the Composer autoloader simultaneously.
     *
     * Parameters:
     *   - array<string, string> $mappings: Associative array of namespace-to-path mappings.
     *
     * Returns:
     *   - array<string, bool>: Results of each registration attempt.
     */
    public function registerMultipleNamespaces(array $mappings): array;

    /**
     * Applies all registered mappings to the active autoloader.
     * Finalizes the registration process by ensuring all namespace
     * mappings are active and available for class resolution.
     *
     * Returns:
     *   - bool: True if the registration was successful, false otherwise.
     */
    public function applyRegistrations(): bool;
}