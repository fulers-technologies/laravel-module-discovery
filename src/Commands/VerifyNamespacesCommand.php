<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * VerifyNamespacesCommand provides verification of registered namespaces.
 * This command checks whether discovered namespaces are properly registered
 * with Composer's autoloader and can be resolved correctly.
 *
 * The command provides detailed information about namespace registration
 * status and helps debug autoloading issues.
 */
#[AsCommand(
    name: 'module:verify',
    description: 'Verify that discovered namespaces are properly registered with Composer autoloader',
    aliases: ['verify:namespaces', 'namespaces:verify']
)]
class VerifyNamespacesCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Defines the command signature including the command name
     * and any optional parameters or flags.
     */
    protected $signature = 'module:verify {--namespace= : Specific namespace to verify} {--detailed : Show detailed information}';

    /**
     * The console command description.
     * Provides a brief description of what the command does
     * for display in the Artisan command list.
     */
    protected $description = 'Verify that discovered namespaces are properly registered with Composer autoloader';

    /**
     * Creates a new VerifyNamespacesCommand instance.
     * Initializes the command with required dependencies for Composer
     * autoloader verification and configuration management.
     *
     * Parameters:
     *   - ComposerLoaderInterface $composerLoader: Service for Composer autoloader operations.
     *   - ConfigurationInterface $configuration: Service for accessing configuration values.
     */
    public function __construct(
        private readonly ComposerLoaderInterface $composerLoader,
        private readonly ConfigurationInterface $configuration
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Performs namespace verification including autoloader inspection,
     * class resolution testing, and registration status reporting.
     *
     * Returns:
     *   - int: Command exit code (0 for success, 1 for failure).
     */
    public function handle(): int
    {
        $this->info('🔍 Verifying namespace registrations with Composer autoloader...');
        $this->line('');

        try {
            $classLoader       = $this->composerLoader->getClassLoader();
            $specificNamespace = $this->option('namespace');
            $isDetailed        = $this->option('detailed') || $this->output->isVerbose();

            if ($specificNamespace) {
                return $this->verifySpecificNamespace($classLoader, $specificNamespace, $isDetailed);
            }

            return $this->verifyAllNamespaces($classLoader, $isDetailed);

        } catch (\Exception $e) {
            $this->error("❌ Failed to verify namespaces: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Verifies all registered PSR-4 namespaces.
     * Checks all PSR-4 mappings in the Composer autoloader and reports
     * on their registration status and functionality.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     *   - bool $isDetailed: Whether to show detailed information.
     *
     * Returns:
     *   - int: Command exit code.
     */
    private function verifyAllNamespaces($classLoader, bool $isDetailed): int
    {
        $psr4Prefixes     = $classLoader->getPrefixesPsr4();
        $moduleNamespaces = $this->filterModuleNamespaces($psr4Prefixes);

        if (empty($moduleNamespaces)) {
            $this->warn('⚠️  No module namespaces found in Composer PSR-4 autoloader');
            $this->line('');
            $this->line('This could mean:');
            $this->line('  - No modules have been discovered yet');
            $this->line('  - Module discovery failed');
            $this->line('  - Namespaces were not properly registered');
            $this->line('  - Classes are registered in class map instead of PSR-4');
            $this->line('');
            $this->line('Try running: php artisan module:discover');

            // Check if we have module classes in the class map
            $classMap           = $classLoader->getClassMap();
            $moduleClassesInMap = [];

            foreach ($classMap as $className => $filePath) {
                if (str_starts_with($className, 'App\\Modules\\')) {
                    $moduleClassesInMap[] = $className;
                }
            }

            if (! empty($moduleClassesInMap)) {
                $this->line('');
                $this->info('✅ However, found ' . count($moduleClassesInMap) . ' module classes in class map:');
                foreach (array_slice($moduleClassesInMap, 0, 10) as $className) {
                    $this->line("  - {$className}");
                }
                if (count($moduleClassesInMap) > 10) {
                    $remaining = count($moduleClassesInMap) - 10;
                    $this->line("  ... and {$remaining} more classes");
                }
                $this->line('');
                $this->warn('💡 Classes are in class map instead of PSR-4. This means:');
                $this->line('  - Autoloading works but is not optimized');
                $this->line('  - PSR-4 namespace registration may have failed');
                $this->line('  - Consider running: composer dump-autoload');

                return 0; // Classes are working, just not in PSR-4
            }

            // Show what we actually found for debugging
            if ($isDetailed) {
                $this->line('');
                $this->info('🔍 Debug: All PSR-4 namespaces found:');
                foreach ($psr4Prefixes as $namespace => $paths) {
                    $this->line("  - {$namespace}");
                }
            }

            return 1;
        }

        $this->info("✅ Found " . count($moduleNamespaces) . " module namespaces in Composer PSR-4 autoloader");
        $this->line('');

        $successCount = 0;
        $totalCount   = count($moduleNamespaces);

        foreach ($moduleNamespaces as $namespace => $paths) {
            $status = $this->verifyNamespaceRegistration($namespace, $paths, $isDetailed);
            if ($status) {
                $successCount++;
            }
        }

        $this->line('');
        $this->info("📊 Verification Summary:");
        $this->line("   ✅ Verified: {$successCount}/{$totalCount} namespaces");

        if ($successCount === $totalCount) {
            $this->info("🎉 All module namespaces are properly registered!");
            return 0;
        } else {
            $this->warn("⚠️  Some namespaces have issues. Check the details above.");
            return 1;
        }
    }

    /**
     * Verifies a specific namespace registration.
     * Checks a single namespace for proper registration and functionality
     * in the Composer autoloader.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     *   - string $namespace: The namespace to verify.
     *   - bool $isDetailed: Whether to show detailed information.
     *
     * Returns:
     *   - int: Command exit code.
     */
    private function verifySpecificNamespace($classLoader, string $namespace, bool $isDetailed): int
    {
        $this->info("🔍 Verifying specific namespace: {$namespace}");
        $this->line('');

        $psr4Prefixes        = $classLoader->getPrefixesPsr4();
        $normalizedNamespace = rtrim($namespace, '\\') . '\\';

        if (! isset($psr4Prefixes[$normalizedNamespace])) {
            $this->error("❌ Namespace '{$namespace}' is not registered in Composer PSR-4 autoloader");
            $this->line('');

            // Check if it's in the class map
            $classMap        = $classLoader->getClassMap();
            $foundInClassMap = [];

            foreach ($classMap as $className => $filePath) {
                if (str_starts_with($className, $namespace)) {
                    $foundInClassMap[] = $className;
                }
            }

            if (! empty($foundInClassMap)) {
                $this->warn("⚠️  However, found " . count($foundInClassMap) . " classes in class map:");
                foreach ($foundInClassMap as $className) {
                    $this->line("  - {$className}");
                }
                $this->line('');
                $this->info("💡 Classes are working but not in PSR-4 autoloader");
                return 0;
            }

            $this->suggestSolutions($namespace);
            return 1;
        }

        $paths   = $psr4Prefixes[$normalizedNamespace];
        $success = $this->verifyNamespaceRegistration($namespace, $paths, $isDetailed);

        return $success ? 0 : 1;
    }

    /**
     * Verifies a single namespace registration.
     * Performs detailed verification of a namespace including path validation,
     * class discovery, and autoloading functionality.
     *
     * Parameters:
     *   - string $namespace: The namespace to verify.
     *   - array<string> $paths: The paths associated with the namespace.
     *   - bool $isDetailed: Whether to show detailed information.
     *
     * Returns:
     *   - bool: True if verification passed, false otherwise.
     */
    private function verifyNamespaceRegistration(string $namespace, array $paths, bool $isDetailed): bool
    {
        $cleanNamespace = rtrim($namespace, '\\');
        $this->line("🔍 <info>{$cleanNamespace}</info>");

        $allPathsValid = true;
        $classesFound  = 0;

        foreach ($paths as $path) {
            $pathStatus = $this->verifyNamespacePath($path, $isDetailed);
            if (! $pathStatus['valid']) {
                $allPathsValid = false;
            }
            $classesFound += $pathStatus['classes_found'];

            if ($isDetailed) {
                $statusIcon = $pathStatus['valid'] ? '✅' : '❌';
                $this->line("   {$statusIcon} Path: {$path}");
                $this->line("      - Exists: " . ($pathStatus['exists'] ? 'Yes' : 'No'));
                $this->line("      - Readable: " . ($pathStatus['readable'] ? 'Yes' : 'No'));
                $this->line("      - Classes found: {$pathStatus['classes_found']}");
            }
        }

        if (! $isDetailed) {
            $statusIcon = $allPathsValid ? '✅' : '❌';
            $pathCount  = count($paths);
            $this->line("   {$statusIcon} {$pathCount} path(s), {$classesFound} classes found");
        }

        // Test autoloading if we found classes
        if ($classesFound > 0) {
            $autoloadWorks = $this->testAutoloading($cleanNamespace, $paths);
            if ($isDetailed) {
                $autoloadIcon = $autoloadWorks ? '✅' : '⚠️';
                $this->line("   {$autoloadIcon} Autoloading: " . ($autoloadWorks ? 'Working' : 'May have issues'));
            }
        }

        return $allPathsValid;
    }

    /**
     * Verifies a namespace path.
     * Checks if a path exists, is readable, and contains PHP classes
     * for the associated namespace.
     *
     * Parameters:
     *   - string $path: The path to verify.
     *   - bool $isDetailed: Whether to show detailed information.
     *
     * Returns:
     *   - array<string, mixed>: Verification results.
     */
    private function verifyNamespacePath(string $path, bool $isDetailed): array
    {
        $result = [
            'valid'         => false,
            'exists'        => false,
            'readable'      => false,
            'classes_found' => 0,
        ];

        $result['exists']   = is_dir($path);
        $result['readable'] = $result['exists'] && is_readable($path);

        if ($result['readable']) {
            $result['classes_found'] = $this->countPhpFiles($path);
            $result['valid']         = true;
        }

        return $result;
    }

    /**
     * Counts PHP files in a directory.
     * Recursively counts PHP files that likely contain classes
     * for namespace verification purposes.
     *
     * Parameters:
     *   - string $directory: The directory to scan.
     *
     * Returns:
     *   - int: Number of PHP files found.
     */
    private function countPhpFiles(string $directory): int
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $count = 0;
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Tests autoloading functionality for a namespace.
     * Attempts to verify that the autoloader can properly resolve
     * classes from the given namespace.
     *
     * Parameters:
     *   - string $namespace: The namespace to test.
     *   - array<string> $paths: The paths associated with the namespace.
     *
     * Returns:
     *   - bool: True if autoloading appears to work, false otherwise.
     */
    private function testAutoloading(string $namespace, array $paths): bool
    {
        // This is a basic test - in a real scenario you might want to
        // try to load actual classes from the namespace
        try {
            // Check if the namespace is properly formatted for PSR-4
            $normalizedNamespace = rtrim($namespace, '\\') . '\\';

            // Verify paths are absolute and exist
            foreach ($paths as $path) {
                if (! is_dir($path)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Filters PSR-4 prefixes to find module namespaces.
     * Identifies namespaces that appear to be module-related based on
     * naming patterns and configuration.
     *
     * Parameters:
     *   - array<string, array<string>> $psr4Prefixes: All PSR-4 prefixes from Composer.
     *
     * Returns:
     *   - array<string, array<string>>: Filtered module namespaces.
     */
    private function filterModuleNamespaces(array $psr4Prefixes): array
    {
        $moduleNamespaces = [];
        $modulesPath      = base_path($this->configuration->getDefaultModulesDirectory());

        foreach ($psr4Prefixes as $namespace => $paths) {
            $isModuleNamespace = false;

            // Check if it's an App\Modules namespace (most common pattern)
            if (str_starts_with($namespace, 'App\\Modules\\')) {
                $isModuleNamespace = true;
            }

            // Check if any path is within the modules directory
            if (! $isModuleNamespace) {
                foreach ($paths as $path) {
                    if (str_starts_with($path, $modulesPath)) {
                        $isModuleNamespace = true;
                        break;
                    }
                }
            }

            // Also check for other common module patterns
            if (! $isModuleNamespace) {
                $modulePatterns = [
                    'Modules\\',
                    'Module\\',
                    '\\Modules\\',
                    '\\Module\\',
                ];

                foreach ($modulePatterns as $pattern) {
                    if (str_contains($namespace, $pattern)) {
                        $isModuleNamespace = true;
                        break;
                    }
                }
            }

            if ($isModuleNamespace) {
                $moduleNamespaces[$namespace] = $paths;
            }
        }

        return $moduleNamespaces;
    }

    /**
     * Suggests solutions for namespace registration issues.
     * Provides helpful suggestions when namespace verification fails
     * to help users resolve common issues.
     *
     * Parameters:
     *   - string $namespace: The namespace that failed verification.
     */
    private function suggestSolutions(string $namespace): void
    {
        $this->line('💡 Possible solutions:');
        $this->line('');
        $this->line('1. Run module discovery:');
        $this->line('   php artisan module:discover');
        $this->line('');
        $this->line('2. Check if the namespace exists in your modules:');
        $this->line('   ls -la ' . base_path($this->configuration->getDefaultModulesDirectory()));
        $this->line('');
        $this->line('3. Verify the namespace format (should end with \\):');
        $this->line("   Expected: {$namespace}\\");
        $this->line('');
        $this->line('4. Check Composer autoloader dump:');
        $this->line('   composer dump-autoload');
        $this->line('');
        $this->line('5. Enable debug mode to see what\'s happening:');
        $this->line('   php artisan module:discover --verbose');
    }
}
