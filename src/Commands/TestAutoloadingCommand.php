<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * TestAutoloadingCommand tests actual class autoloading functionality.
 * This command attempts to load classes from discovered namespaces to verify
 * that the autoloader registration is working correctly in practice.
 *
 * The command provides real-world testing of autoloading functionality
 * by attempting to instantiate or reference actual classes.
 */
#[AsCommand(
    name: 'module:test-autoloading',
    description: 'Test actual autoloading of classes from discovered namespaces',
    aliases: ['test:autoloading', 'autoloading:test']
)]
class TestAutoloadingCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Defines the command signature including the command name
     * and any optional parameters or flags.
     */
    protected $signature = 'module:test-autoloading {--namespace= : Specific namespace to test} {--class= : Specific class to test}';

    /**
     * The console command description.
     * Provides a brief description of what the command does
     * for display in the Artisan command list.
     */
    protected $description = 'Test actual autoloading of classes from discovered namespaces';

    /**
     * Creates a new TestAutoloadingCommand instance.
     * Initializes the command with required dependencies for autoloading
     * testing and verification.
     *
     * Parameters:
     *   - ComposerLoaderInterface $composerLoader: Service for Composer autoloader operations.
     */
    public function __construct(
        private readonly ComposerLoaderInterface $composerLoader
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * Performs actual autoloading tests by attempting to load classes
     * from discovered namespaces and reporting results.
     *
     * Returns:
     *   - int: Command exit code (0 for success, 1 for failure).
     */
    public function handle(): int
    {
        $this->info('🧪 Testing Autoloading Functionality');
        $this->line('');

        try {
            $specificNamespace = $this->option('namespace');
            $specificClass     = $this->option('class');

            if ($specificClass) {
                return $this->testSpecificClass($specificClass);
            }

            if ($specificNamespace) {
                return $this->testNamespaceClasses($specificNamespace);
            }

            return $this->testAllModuleClasses();

        } catch (\Exception $e) {
            $this->error("❌ Failed to test autoloading: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Tests autoloading for a specific class.
     * Attempts to load and verify a specific class to test
     * autoloading functionality.
     *
     * Parameters:
     *   - string $className: The fully qualified class name to test.
     *
     * Returns:
     *   - int: Command exit code.
     */
    private function testSpecificClass(string $className): int
    {
        $this->info("🎯 Testing specific class: {$className}");
        $this->line('');

        $result = $this->testClassAutoloading($className);

        if ($result['success']) {
            $this->info("✅ Class '{$className}' loaded successfully");
            $this->showClassInfo($className, $result);
            return 0;
        } else {
            $this->error("❌ Failed to load class '{$className}': " . $result['error']);
            return 1;
        }
    }

    /**
     * Tests autoloading for classes in a specific namespace.
     * Discovers and tests classes within a given namespace to verify
     * autoloading functionality.
     *
     * Parameters:
     *   - string $namespace: The namespace to test.
     *
     * Returns:
     *   - int: Command exit code.
     */
    private function testNamespaceClasses(string $namespace): int
    {
        $this->info("🎯 Testing namespace: {$namespace}");
        $this->line('');

        $classLoader         = $this->composerLoader->getClassLoader();
        $psr4Prefixes        = $classLoader->getPrefixesPsr4();
        $normalizedNamespace = rtrim($namespace, '\\') . '\\';

        if (! isset($psr4Prefixes[$normalizedNamespace])) {
            $this->error("❌ Namespace '{$namespace}' is not registered in PSR-4 autoloader");

            // Check if it's in the class map instead
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
                $this->info("Testing classes from class map:");
                return $this->testMultipleClasses($foundInClassMap);
            }

            return 1;
        }

        $paths   = $psr4Prefixes[$normalizedNamespace];
        $classes = $this->discoverClassesInPaths($paths, $namespace);

        if (empty($classes)) {
            $this->warn("⚠️  No classes found in namespace '{$namespace}'");
            return 1;
        }

        return $this->testMultipleClasses($classes);
    }

    /**
     * Tests autoloading for all module classes.
     * Discovers and tests classes from all registered module namespaces
     * to provide comprehensive autoloading verification.
     *
     * Returns:
     *   - int: Command exit code.
     */
    private function testAllModuleClasses(): int
    {
        $this->info("🎯 Testing all module classes");
        $this->line('');

        $classLoader  = $this->composerLoader->getClassLoader();
        $psr4Prefixes = $classLoader->getPrefixesPsr4();

        $moduleClasses    = [];
        $moduleNamespaces = $this->findModuleNamespaces($psr4Prefixes);

        $this->info("Found " . count($moduleNamespaces) . " module namespaces:");
        foreach ($moduleNamespaces as $ns) {
            $this->line("  - " . rtrim($ns, '\\'));
        }
        $this->line('');

        // Discover classes from module namespaces
        foreach ($moduleNamespaces as $namespace) {
            $paths         = $psr4Prefixes[$namespace];
            $classes       = $this->discoverClassesInPaths($paths, rtrim($namespace, '\\'));
            $moduleClasses = array_merge($moduleClasses, $classes);
        }

        // If no PSR-4 namespaces found, check class map
        if (empty($moduleClasses)) {
            $this->warn("⚠️  No module classes found in PSR-4 namespaces");
            $this->line('');
            $this->line('This could mean:');
            $this->line('  - No modules have been discovered yet');
            $this->line('  - Module classes don\'t follow PSR-4 naming conventions');
            $this->line('  - Classes are registered in class map instead of PSR-4');
            $this->line('');
            $this->line('Try running: php artisan module:discover');
            $this->line('');

            // Let's check the class map for module classes
            $this->info("🔍 Looking for module classes in class map...");
            $moduleClasses = $this->findModuleClassesInClassMap();

            if (! empty($moduleClasses)) {
                $this->info("Found " . count($moduleClasses) . " module classes in class map:");
                foreach ($moduleClasses as $className) {
                    $this->line("  - {$className}");
                }
                $this->line('');
                return $this->testMultipleClasses($moduleClasses);
            }

            return 1;
        }

        $this->info("Found " . count($moduleClasses) . " classes to test");
        $this->line('');

        return $this->testMultipleClasses($moduleClasses);
    }

    /**
     * Finds module classes in the Composer class map.
     * Searches the class map for classes that appear to be module-related.
     *
     * Returns:
     *   - array<string>: Array of module class names found in class map.
     */
    private function findModuleClassesInClassMap(): array
    {
        $classLoader   = $this->composerLoader->getClassLoader();
        $classMap      = $classLoader->getClassMap();
        $moduleClasses = [];

        foreach ($classMap as $className => $filePath) {
            // Check if it's a module class
            if (str_starts_with($className, 'App\\Modules\\')) {
                $moduleClasses[] = $className;
            }
        }

        return $moduleClasses;
    }

    /**
     * Finds module namespaces from PSR-4 prefixes.
     * Identifies namespaces that appear to be module-related based on
     * naming patterns and path analysis.
     *
     * Parameters:
     *   - array<string, array<string>> $psr4Prefixes: All PSR-4 prefixes from Composer.
     *
     * Returns:
     *   - array<string>: Array of module namespace prefixes.
     */
    private function findModuleNamespaces(array $psr4Prefixes): array
    {
        $moduleNamespaces = [];
        $modulesPath      = base_path('app/Modules');

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
                $moduleNamespaces[] = $namespace;
            }
        }

        return $moduleNamespaces;
    }

    /**
     * Tests autoloading for multiple classes.
     * Performs autoloading tests on an array of class names and reports
     * success/failure statistics.
     *
     * Parameters:
     *   - array<string> $classes: Array of fully qualified class names to test.
     *
     * Returns:
     *   - int: Command exit code.
     */
    private function testMultipleClasses(array $classes): int
    {
        $successCount = 0;
        $failureCount = 0;
        $results      = [];

        foreach ($classes as $className) {
            $result              = $this->testClassAutoloading($className);
            $results[$className] = $result;

            if ($result['success']) {
                $successCount++;
                $this->line("✅ {$className}");
            } else {
                $failureCount++;
                $this->line("❌ {$className} - " . $result['error']);
            }
        }

        $this->line('');
        $this->info("📊 Test Results:");
        $this->line("   ✅ Successful: {$successCount}");
        $this->line("   ❌ Failed: {$failureCount}");
        $this->line("   📈 Success Rate: " . round(($successCount / count($classes)) * 100, 1) . "%");

        return $failureCount === 0 ? 0 : 1;
    }

    /**
     * Tests autoloading for a single class.
     * Attempts to load a class and gather information about the loading process
     * and the loaded class.
     *
     * Parameters:
     *   - string $className: The fully qualified class name to test.
     *
     * Returns:
     *   - array<string, mixed>: Test results including success status and class info.
     */
    private function testClassAutoloading(string $className): array
    {
        try {
            // Test if class exists (this triggers autoloading)
            if (! class_exists($className) && ! interface_exists($className) && ! trait_exists($className)) {
                return [
                    'success' => false,
                    'error'   => 'Class/Interface/Trait does not exist or could not be autoloaded',
                ];
            }

            // Get reflection information
            $reflection = new \ReflectionClass($className);

            return [
                'success'     => true,
                'type'        => $this->getClassType($reflection),
                'file'        => $reflection->getFileName(),
                'namespace'   => $reflection->getNamespaceName(),
                'methods'     => count($reflection->getMethods()),
                'properties'  => count($reflection->getProperties()),
                'is_abstract' => $reflection->isAbstract(),
                'is_final'    => $reflection->isFinal(),
                'parent'      => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
                'interfaces'  => array_keys($reflection->getInterfaces()),
                'traits'      => array_keys($reflection->getTraits()),
            ];

        } catch (\ReflectionException $e) {
            return [
                'success' => false,
                'error'   => 'Reflection failed: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Autoloading failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Discovers classes in the given paths.
     * Scans directory paths to find PHP files and extract class names
     * for autoloading testing.
     *
     * Parameters:
     *   - array<string> $paths: Array of directory paths to scan.
     *   - string $baseNamespace: The base namespace for the paths.
     *
     * Returns:
     *   - array<string>: Array of discovered fully qualified class names.
     */
    private function discoverClassesInPaths(array $paths, string $baseNamespace): array
    {
        $classes = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $className = $this->extractClassNameFromFile($file->getPathname(), $path, $baseNamespace);
                    if ($className) {
                        $classes[] = $className;
                    }
                }
            } catch (\Exception $e) {
                // Skip problematic directories
                continue;
            }
        }

        return array_unique($classes);
    }

    /**
     * Extracts class name from a PHP file.
     * Analyzes a PHP file to determine the fully qualified class name
     * based on file path and namespace structure.
     *
     * Parameters:
     *   - string $filePath: The path to the PHP file.
     *   - string $basePath: The base path for the namespace.
     *   - string $baseNamespace: The base namespace.
     *
     * Returns:
     *   - string|null: The fully qualified class name or null if not found.
     */
    private function extractClassNameFromFile(string $filePath, string $basePath, string $baseNamespace): ?string
    {
        // Calculate relative path
        $relativePath = str_replace($basePath, '', $filePath);
        $relativePath = ltrim($relativePath, '/\\');

        // Remove .php extension
        $relativePath = substr($relativePath, 0, -4);

        // Convert path separators to namespace separators
        $namespacePart = str_replace(['/', '\\'], '\\', $relativePath);

        // Combine base namespace with relative namespace
        $fullClassName = $baseNamespace . '\\' . $namespacePart;

        return $fullClassName;
    }

    /**
     * Shows detailed information about a loaded class.
     * Displays comprehensive information about a successfully loaded class
     * including its properties, methods, and relationships.
     *
     * Parameters:
     *   - string $className: The class name.
     *   - array<string, mixed> $classInfo: Information about the class.
     */
    private function showClassInfo(string $className, array $classInfo): void
    {
        $this->line('');
        $this->info("📋 Class Information:");
        $this->line("   Type: " . $classInfo['type']);
        $this->line("   File: " . $classInfo['file']);
        $this->line("   Namespace: " . $classInfo['namespace']);
        $this->line("   Methods: " . $classInfo['methods']);
        $this->line("   Properties: " . $classInfo['properties']);

        if ($classInfo['is_abstract']) {
            $this->line("   Abstract: Yes");
        }

        if ($classInfo['is_final']) {
            $this->line("   Final: Yes");
        }

        if ($classInfo['parent']) {
            $this->line("   Parent: " . $classInfo['parent']);
        }

        if (! empty($classInfo['interfaces'])) {
            $this->line("   Interfaces: " . implode(', ', $classInfo['interfaces']));
        }

        if (! empty($classInfo['traits'])) {
            $this->line("   Traits: " . implode(', ', $classInfo['traits']));
        }
    }

    /**
     * Gets the type of a class (class, interface, trait).
     * Determines whether a ReflectionClass represents a class,
     * interface, or trait.
     *
     * Parameters:
     *   - \ReflectionClass $reflection: The reflection instance.
     *
     * Returns:
     *   - string: The type of the class.
     */
    private function getClassType(\ReflectionClass $reflection): string
    {
        if ($reflection->isInterface()) {
            return 'Interface';
        }

        if ($reflection->isTrait()) {
            return 'Trait';
        }

        return 'Class';
    }
}
