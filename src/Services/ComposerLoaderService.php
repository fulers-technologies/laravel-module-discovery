<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Services;

use Composer\Autoload\ClassLoader;
use LaravelModuleDiscovery\ComposerHook\Constants\DirectoryConstants;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;

/**
 * ComposerLoaderService implements Composer autoloader integration functionality.
 * This service handles the registration of discovered namespaces with Composer's
 * ClassLoader and manages PSR-4 autoloading mappings for module discovery.
 *
 * The service provides methods to interact with Composer's autoloader system
 * and register new namespace-to-directory mappings dynamically.
 */
class ComposerLoaderService implements ComposerLoaderInterface
{
    /**
     * The Composer ClassLoader instance.
     * Represents the active autoloader that handles class loading
     * and namespace resolution for the application.
     */
    private ?ClassLoader $classLoader = null;

    /**
     * Registry of registered namespace mappings.
     * Tracks all namespace-to-path mappings that have been
     * registered with the Composer autoloader.
     *
     * @var array<string, string>
     */
    private array $registeredMappings = [];

    /**
     * Creates a new ComposerLoaderService instance using static factory method.
     * Provides a convenient way to instantiate the service and automatically
     * locate the Composer autoloader without using the new keyword.
     *
     * Returns:
     *   - static: A new ComposerLoaderService instance.
     *
     * @throws ModuleDiscoveryException When Composer autoloader cannot be found.
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Retrieves the Composer ClassLoader instance.
     * Returns the active Composer autoloader instance that can be used
     * to register additional namespaces and PSR-4 mappings.
     *
     * Returns:
     *   - ClassLoader: The Composer ClassLoader instance.
     *
     * @throws ModuleDiscoveryException When the ClassLoader cannot be obtained.
     */
    public function getClassLoader(): ClassLoader
    {
        if ($this->classLoader === null) {
            $this->classLoader = $this->loadComposerAutoloader();
        }

        return $this->classLoader;
    }

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
    public function registerNamespace(string $namespace, string $path): bool
    {
        try {
            $loader = $this->getClassLoader();
            
            // Ensure namespace ends with backslash for PSR-4 compliance
            $normalizedNamespace = rtrim($namespace, '\\') . '\\';
            
            // Register the PSR-4 mapping
            $loader->addPsr4($normalizedNamespace, $path);
            
            // Track the registration
            $this->registeredMappings[$normalizedNamespace] = $path;
            
            return true;
            
        } catch (\Exception $e) {
            // Log the error but don't throw to allow continuation
            error_log("Failed to register namespace '{$namespace}': " . $e->getMessage());
            return false;
        }
    }

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
    public function registerMultipleNamespaces(array $mappings): array
    {
        $results = [];
        
        foreach ($mappings as $namespace => $path) {
            $results[$namespace] = $this->registerNamespace($namespace, $path);
        }
        
        return $results;
    }

    /**
     * Applies all registered mappings to the active autoloader.
     * Finalizes the registration process by ensuring all namespace
     * mappings are active and available for class resolution.
     *
     * Returns:
     *   - bool: True if the registration was successful, false otherwise.
     */
    public function applyRegistrations(): bool
    {
        try {
            $loader = $this->getClassLoader();
            
            // Re-register the autoloader to ensure all mappings are active
            $loader->register(true);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Failed to apply namespace registrations: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Loads the Composer ClassLoader from the autoload file.
     * Locates and includes the Composer autoload.php file to obtain
     * the ClassLoader instance for namespace registration.
     *
     * Returns:
     *   - ClassLoader: The loaded Composer ClassLoader instance.
     *
     * @throws ModuleDiscoveryException When the autoloader cannot be loaded.
     */
    private function loadComposerAutoloader(): ClassLoader
    {
        $autoloadPath = $this->findAutoloadPath();
        
        if (!file_exists($autoloadPath)) {
            throw ModuleDiscoveryException::directoryNotAccessible(
                $autoloadPath,
                'Composer autoload file not found'
            );
        }
        
        $loader = require $autoloadPath;
        
        if (!$loader instanceof ClassLoader) {
            throw new ModuleDiscoveryException(
                'Failed to load Composer ClassLoader from autoload file'
            );
        }
        
        return $loader;
    }

    /**
     * Finds the path to the Composer autoload file.
     * Searches for the autoload.php file in common locations
     * relative to the application structure.
     *
     * Returns:
     *   - string: The path to the Composer autoload file.
     */
    private function findAutoloadPath(): string
    {
        $possiblePaths = [
            // Standard Laravel installation
            base_path(DirectoryConstants::VENDOR_DIRECTORY . '/' . DirectoryConstants::COMPOSER_AUTOLOAD_FILE),
            // Fallback for non-Laravel contexts
            __DIR__ . '/../../' . DirectoryConstants::VENDOR_DIRECTORY . '/' . DirectoryConstants::COMPOSER_AUTOLOAD_FILE,
            // Current directory vendor (for package development)
            getcwd() . '/' . DirectoryConstants::VENDOR_DIRECTORY . '/' . DirectoryConstants::COMPOSER_AUTOLOAD_FILE,
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default to the first path if none found (will trigger error in loadComposerAutoloader)
        return $possiblePaths[0];
    }

    /**
     * Gets statistics about registered namespace mappings.
     * Returns information about the namespaces that have been
     * registered with the Composer autoloader.
     *
     * Returns:
     *   - array<string, mixed>: Statistics about registered mappings.
     */
    public function getRegistrationStats(): array
    {
        return [
            'registered_count' => count($this->registeredMappings),
            'registered_mappings' => $this->registeredMappings,
            'autoloader_available' => $this->classLoader !== null,
        ];
    }

    /**
     * Clears all registered mappings from memory.
     * Removes the tracking of registered namespace mappings
     * but does not affect the actual Composer autoloader registrations.
     */
    public function clearRegistrationTracking(): void
    {
        $this->registeredMappings = [];
    }
}