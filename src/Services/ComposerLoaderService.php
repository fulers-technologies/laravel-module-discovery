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

            // Ensure path is absolute and normalized
            $normalizedPath = $this->normalizePath($path);

            // Debug output
            $this->debug("🔧 Registering namespace: {$normalizedNamespace} => {$normalizedPath}");

            // Get current PSR-4 prefixes
            $currentPrefixes = $loader->getPrefixesPsr4();

            // Add our namespace to the existing prefixes
            if (isset($currentPrefixes[$normalizedNamespace])) {
                // Namespace already exists, add path to existing paths
                $existingPaths = $currentPrefixes[$normalizedNamespace];
                if (!in_array($normalizedPath, $existingPaths)) {
                    $existingPaths[] = $normalizedPath;
                    $loader->setPsr4($normalizedNamespace, $existingPaths);
                    $this->debug("📝 Added path to existing namespace: {$normalizedNamespace}");
                }
            } else {
                // New namespace, set it directly
                $loader->setPsr4($normalizedNamespace, [$normalizedPath]);
                $this->debug("🆕 Added new namespace: {$normalizedNamespace}");
            }

            // Also use addPsr4 for additional compatibility
            $loader->addPsr4($normalizedNamespace, $normalizedPath);

            // Track the registration
            $this->registeredMappings[$normalizedNamespace] = $normalizedPath;

            // Verify registration immediately
            $updatedPrefixes = $loader->getPrefixesPsr4();
            if (isset($updatedPrefixes[$normalizedNamespace])) {
                $this->debug("✅ Successfully registered and verified namespace: {$normalizedNamespace}");
                return true;
            } else {
                $this->debug("❌ Registration verification failed for: {$normalizedNamespace}");
                return false;
            }

        } catch (\Exception $e) {
            $this->debug("❌ Failed to register namespace '{$namespace}': " . $e->getMessage());
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

        $this->debug("🔧 Registering " . count($mappings) . " namespaces in batch");

        foreach ($mappings as $namespace => $path) {
            $results[$namespace] = $this->registerNamespace($namespace, $path);
        }

        $successCount = count(array_filter($results));
        $this->debug("✅ Batch registration completed: {$successCount}/" . count($mappings) . " successful");

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

            $this->debug("🔧 Applying registrations to autoloader");

            // Force re-registration of the autoloader to ensure all mappings are active
            $loader->unregister();
            $loader->register(true);

            $this->debug("✅ Autoloader re-registered successfully");

            // Verify that our namespaces are actually registered
            $psr4Prefixes = $loader->getPrefixesPsr4();
            $registeredCount = 0;

            foreach ($this->registeredMappings as $namespace => $path) {
                if (isset($psr4Prefixes[$namespace])) {
                    $registeredCount++;
                    $this->debug("✅ Verified namespace in autoloader: {$namespace}");
                } else {
                    $this->debug("❌ Namespace not found in autoloader: {$namespace}");
                }
            }

            $this->debug("📊 Verification: {$registeredCount}/" . count($this->registeredMappings) . " namespaces verified");

            // Write PSR-4 mappings to composer.json if possible
            $this->updateComposerJson();

            return $registeredCount > 0;

        } catch (\Exception $e) {
            $this->debug("❌ Failed to apply registrations: " . $e->getMessage());
            error_log("Failed to apply namespace registrations: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates composer.json with PSR-4 mappings to make them persistent.
     * Adds discovered namespaces to composer.json so they persist across
     * composer dump-autoload operations.
     */
    private function updateComposerJson(): void
    {
        try {
            $composerJsonPath = base_path('composer.json');

            if (!file_exists($composerJsonPath)) {
                $this->debug("⚠️ composer.json not found, skipping persistent registration");
                return;
            }

            $composerData = json_decode(file_get_contents($composerJsonPath), true);

            if (!$composerData) {
                $this->debug("⚠️ Failed to parse composer.json, skipping persistent registration");
                return;
            }

            // Initialize autoload.psr-4 if it doesn't exist
            if (!isset($composerData['autoload']['psr-4'])) {
                $composerData['autoload']['psr-4'] = [];
            }

            $updated = false;

            foreach ($this->registeredMappings as $namespace => $path) {
                // Convert absolute path to relative path from project root
                $relativePath = $this->getRelativePath(base_path(), $path);

                // Only add if not already present
                if (!isset($composerData['autoload']['psr-4'][$namespace])) {
                    $composerData['autoload']['psr-4'][$namespace] = $relativePath;
                    $updated = true;
                    $this->debug("📝 Added to composer.json: {$namespace} => {$relativePath}");
                }
            }

            if ($updated) {
                // Write back to composer.json with pretty formatting
                $jsonContent = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                file_put_contents($composerJsonPath, $jsonContent);
                $this->debug("✅ Updated composer.json with PSR-4 mappings");

                // Suggest running composer dump-autoload
                $this->debug("💡 Run 'composer dump-autoload' to make changes permanent");
            } else {
                $this->debug("ℹ️ No updates needed for composer.json");
            }

        } catch (\Exception $e) {
            $this->debug("⚠️ Failed to update composer.json: " . $e->getMessage());
            // Don't throw - this is not critical for functionality
        }
    }

    /**
     * Gets relative path from base to target.
     * Calculates the relative path from one directory to another.
     *
     * Parameters:
     *   - string $from: The base directory.
     *   - string $to: The target directory.
     *
     * Returns:
     *   - string: The relative path.
     */
    private function getRelativePath(string $from, string $to): string
    {
        $from = rtrim(str_replace('\\', '/', $from), '/');
        $to = rtrim(str_replace('\\', '/', $to), '/');

        $fromParts = explode('/', $from);
        $toParts = explode('/', $to);

        // Find common base
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));

        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }

        // Build relative path
        $relativeParts = [];

        // Add .. for each remaining part in from
        for ($i = $commonLength; $i < count($fromParts); $i++) {
            $relativeParts[] = '..';
        }

        // Add remaining parts from to
        for ($i = $commonLength; $i < count($toParts); $i++) {
            $relativeParts[] = $toParts[$i];
        }

        return empty($relativeParts) ? './' : implode('/', $relativeParts) . '/';
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

        $this->debug("✅ Loaded Composer ClassLoader from: {$autoloadPath}");

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
     * Normalizes a file path for cross-platform compatibility.
     * Ensures consistent path formatting regardless of the operating system.
     *
     * Parameters:
     *   - string $path: The path to normalize.
     *
     * Returns:
     *   - string: The normalized path.
     */
    private function normalizePath(string $path): string
    {
        // Convert to absolute path if relative
        if (!$this->isAbsolutePath($path)) {
            $path = base_path($path);
        }

        // Normalize directory separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove trailing separator
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Checks if a path is absolute.
     * Determines whether a path is absolute or relative.
     *
     * Parameters:
     *   - string $path: The path to check.
     *
     * Returns:
     *   - bool: True if the path is absolute, false otherwise.
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix/Linux absolute path
        if (str_starts_with($path, '/')) {
            return true;
        }

        // Windows absolute path (C:\ or similar)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }

        return false;
    }

    /**
     * Debug output function.
     * Outputs debug information if debug mode is enabled.
     *
     * Parameters:
     *   - string $message: The debug message to output.
     */
    private function debug(string $message): void
    {
        // Check if we're in debug mode (you can add configuration for this)
        if (config('module-discovery.development.debug_mode', false)) {
            echo "[COMPOSER-LOADER] {$message}\n";
        }
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
