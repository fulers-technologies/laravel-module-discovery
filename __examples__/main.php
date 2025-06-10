<?php

declare(strict_types=1);

/**
 * Main examples file for Laravel Module Discovery Composer Hook.
 * This file contains various usage examples that demonstrate the functionality
 * of the module discovery system. Uncomment specific examples to test different
 * features and scenarios.
 *
 * Each example is self-contained and can be run independently by uncommenting
 * the relevant function call and commenting out others.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LaravelModuleDiscovery\ComposerHook\Services\ClassDiscoveryService;
use LaravelModuleDiscovery\ComposerHook\Services\NamespaceExtractorService;
use LaravelModuleDiscovery\ComposerHook\Services\PathResolverService;
use LaravelModuleDiscovery\ComposerHook\Services\ComposerLoaderService;

/**
 * Example 1: Basic Class Discovery
 * Demonstrates how to discover classes in a directory and extract their namespaces.
 */
function basicClassDiscoveryExample(): void
{
    echo "=== Basic Class Discovery Example ===\n";
    
    $classDiscovery = ClassDiscoveryService::make();
    $modulesPath = __DIR__ . '/sample_modules';
    
    try {
        $discoveredClasses = $classDiscovery->discoverClasses($modulesPath);
        
        echo "Discovered " . count($discoveredClasses) . " namespaces:\n";
        foreach ($discoveredClasses as $namespace => $path) {
            echo "  - {$namespace} => {$path}\n";
        }
        
        $status = $classDiscovery->getDiscoveryStatus();
        echo "\nDiscovery Status: " . $status['status'] . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example 2: Namespace Extraction from Individual Files
 * Shows how to extract namespaces from specific PHP files.
 */
function namespaceExtractionExample(): void
{
    echo "=== Namespace Extraction Example ===\n";
    
    $extractor = NamespaceExtractorService::make();
    
    // Create a sample PHP file for testing
    $sampleFile = createSamplePhpFile();
    
    try {
        $namespace = $extractor->extractNamespace($sampleFile);
        
        if ($namespace !== null) {
            echo "Extracted namespace: {$namespace}\n";
        } else {
            echo "No namespace found in the file\n";
        }
        
        // Show cache statistics
        $cacheStats = $extractor->getCacheStats();
        echo "Cache size: " . $cacheStats['cache_size'] . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    } finally {
        // Clean up
        if (file_exists($sampleFile)) {
            unlink($sampleFile);
        }
    }
    
    echo "\n";
}

/**
 * Example 3: Path Resolution Operations
 * Demonstrates path normalization and resolution functionality.
 */
function pathResolutionExample(): void
{
    echo "=== Path Resolution Example ===\n";
    
    $pathResolver = PathResolverService::make();
    
    $testPaths = [
        'app/Modules/Blog',
        './src/Services',
        '../vendor/package',
        '/absolute/path/to/modules',
    ];
    
    echo "Path resolution examples:\n";
    foreach ($testPaths as $path) {
        $resolved = $pathResolver->resolveAbsolutePath($path);
        $normalized = $pathResolver->normalizePath($path);
        
        echo "  Original: {$path}\n";
        echo "  Resolved: {$resolved}\n";
        echo "  Normalized: {$normalized}\n";
        echo "  Directory: " . $pathResolver->getDirectoryPath($resolved) . "\n\n";
    }
    
    $cacheStats = $pathResolver->getCacheStats();
    echo "Path cache size: " . $cacheStats['cache_size'] . "\n";
    
    echo "\n";
}

/**
 * Example 4: Composer Autoloader Integration
 * Shows how to register namespaces with Composer's autoloader.
 */
function composerLoaderExample(): void
{
    echo "=== Composer Loader Example ===\n";
    
    $composerLoader = ComposerLoaderService::make();
    
    $testMappings = [
        'Example\\Blog\\Controllers' => __DIR__ . '/sample_modules/blog/controllers',
        'Example\\User\\Services' => __DIR__ . '/sample_modules/user/services',
        'Example\\Common\\Utilities' => __DIR__ . '/sample_modules/common/utilities',
    ];
    
    try {
        echo "Registering namespace mappings:\n";
        $results = $composerLoader->registerMultipleNamespaces($testMappings);
        
        foreach ($results as $namespace => $success) {
            $status = $success ? '✓' : '✗';
            echo "  {$status} {$namespace}\n";
        }
        
        $applicationSuccess = $composerLoader->applyRegistrations();
        echo "\nApplication result: " . ($applicationSuccess ? 'Success' : 'Failed') . "\n";
        
        $stats = $composerLoader->getRegistrationStats();
        echo "Total registered: " . $stats['registered_count'] . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example 5: Complete Discovery and Registration Workflow
 * Demonstrates the full workflow from discovery to autoloader registration.
 */
function completeWorkflowExample(): void
{
    echo "=== Complete Workflow Example ===\n";
    
    // Step 1: Create sample module structure
    $modulesPath = createSampleModuleStructure();
    
    try {
        // Step 2: Discover classes
        $classDiscovery = ClassDiscoveryService::make();
        $discoveredClasses = $classDiscovery->discoverClasses($modulesPath);
        
        echo "Step 1 - Discovery completed:\n";
        echo "Found " . count($discoveredClasses) . " namespaces\n\n";
        
        // Step 3: Register with Composer
        $composerLoader = ComposerLoaderService::make();
        $registrationResults = $composerLoader->registerMultipleNamespaces($discoveredClasses);
        
        echo "Step 2 - Registration results:\n";
        $successCount = count(array_filter($registrationResults));
        echo "Successfully registered: {$successCount}/" . count($registrationResults) . "\n\n";
        
        // Step 4: Apply registrations
        $applicationSuccess = $composerLoader->applyRegistrations();
        echo "Step 3 - Application: " . ($applicationSuccess ? 'Success' : 'Failed') . "\n\n";
        
        // Step 5: Show final statistics
        $discoveryStatus = $classDiscovery->getDiscoveryStatus();
        $loaderStats = $composerLoader->getRegistrationStats();
        
        echo "Final Statistics:\n";
        echo "  Discovery status: " . $discoveryStatus['status'] . "\n";
        echo "  Processing time: " . ($discoveryStatus['statistics']['processing_time'] ?? 0) . "s\n";
        echo "  Registered mappings: " . $loaderStats['registered_count'] . "\n";
        
    } catch (Exception $e) {
        echo "Error in workflow: " . $e->getMessage() . "\n";
    } finally {
        // Clean up
        cleanupSampleModules($modulesPath);
    }
    
    echo "\n";
}

/**
 * Example 6: Error Handling and Edge Cases
 * Demonstrates how the system handles various error conditions.
 */
function errorHandlingExample(): void
{
    echo "=== Error Handling Example ===\n";
    
    $classDiscovery = ClassDiscoveryService::make();
    
    // Test 1: Non-existent directory
    echo "Test 1 - Non-existent directory:\n";
    try {
        $classDiscovery->discoverClasses('/non/existent/directory');
    } catch (Exception $e) {
        echo "  Caught expected error: " . get_class($e) . "\n";
        echo "  Message: " . $e->getMessage() . "\n";
    }
    
    // Test 2: Directory with invalid PHP files
    echo "\nTest 2 - Invalid PHP files:\n";
    $testDir = createDirectoryWithInvalidFiles();
    try {
        $result = $classDiscovery->discoverClasses($testDir);
        echo "  Discovery completed with " . count($result) . " valid namespaces\n";
        
        $status = $classDiscovery->getDiscoveryStatus();
        if (isset($status['statistics']['error_files'])) {
            echo "  Error files encountered: " . count($status['statistics']['error_files']) . "\n";
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    } finally {
        cleanupTestDirectory($testDir);
    }
    
    echo "\n";
}

/**
 * Helper function to create a sample PHP file for testing.
 * Creates a temporary PHP file with proper namespace declaration.
 *
 * Returns:
 *   - string: Path to the created sample file.
 */
function createSamplePhpFile(): string
{
    $content = "<?php\n\nnamespace Example\\Sample\\Testing;\n\nclass SampleClass\n{\n    public function test(): string\n    {\n        return 'Hello from sample class!';\n    }\n}\n";
    $tempFile = tempnam(sys_get_temp_dir(), 'sample_php_');
    file_put_contents($tempFile, $content);
    return $tempFile;
}

/**
 * Helper function to create a sample module directory structure.
 * Creates a temporary directory with sample PHP modules for testing.
 *
 * Returns:
 *   - string: Path to the created modules directory.
 */
function createSampleModuleStructure(): string
{
    $baseDir = sys_get_temp_dir() . '/sample_modules_' . uniqid();
    
    // Create directory structure
    $directories = [
        $baseDir . '/Blog/Controllers',
        $baseDir . '/Blog/Models',
        $baseDir . '/User/Services',
        $baseDir . '/Common/Utilities',
    ];
    
    foreach ($directories as $dir) {
        mkdir($dir, 0777, true);
    }
    
    // Create sample PHP files
    $files = [
        $baseDir . '/Blog/Controllers/BlogController.php' => "<?php\n\nnamespace Example\\Blog\\Controllers;\n\nclass BlogController\n{\n}\n",
        $baseDir . '/Blog/Models/Post.php' => "<?php\n\nnamespace Example\\Blog\\Models;\n\nclass Post\n{\n}\n",
        $baseDir . '/User/Services/UserService.php' => "<?php\n\nnamespace Example\\User\\Services;\n\nclass UserService\n{\n}\n",
        $baseDir . '/Common/Utilities/Helper.php' => "<?php\n\nnamespace Example\\Common\\Utilities;\n\nclass Helper\n{\n}\n",
    ];
    
    foreach ($files as $filePath => $content) {
        file_put_contents($filePath, $content);
    }
    
    return $baseDir;
}

/**
 * Helper function to create a directory with invalid PHP files.
 * Creates a test directory containing files with syntax errors.
 *
 * Returns:
 *   - string: Path to the created test directory.
 */
function createDirectoryWithInvalidFiles(): string
{
    $testDir = sys_get_temp_dir() . '/invalid_files_' . uniqid();
    mkdir($testDir, 0777, true);
    
    // Create files with various issues
    $files = [
        $testDir . '/ValidFile.php' => "<?php\n\nnamespace Test\\Valid;\n\nclass ValidFile\n{\n}\n",
        $testDir . '/InvalidSyntax.php' => "<?php\n\nnamespace Test\\Invalid\n\nclass InvalidSyntax\n{\n", // Missing semicolon and closing brace
        $testDir . '/NoNamespace.php' => "<?php\n\nclass NoNamespace\n{\n}\n",
        $testDir . '/NotPhpFile.txt' => "This is not a PHP file",
    ];
    
    foreach ($files as $filePath => $content) {
        file_put_contents($filePath, $content);
    }
    
    return $testDir;
}

/**
 * Helper function to clean up sample modules directory.
 * Recursively removes the sample modules directory and all its contents.
 *
 * Parameters:
 *   - string $directory: The directory to clean up.
 */
function cleanupSampleModules(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    
    rmdir($directory);
}

/**
 * Helper function to clean up test directories.
 * Removes test directories and their contents.
 *
 * Parameters:
 *   - string $directory: The directory to clean up.
 */
function cleanupTestDirectory(string $directory): void
{
    cleanupSampleModules($directory);
}

// Main execution - uncomment the example you want to run
echo "Laravel Module Discovery Composer Hook - Examples\n";
echo "================================================\n\n";

// Uncomment one of the following lines to run specific examples:

basicClassDiscoveryExample();
// namespaceExtractionExample();
// pathResolutionExample();
// composerLoaderExample();
// completeWorkflowExample();
// errorHandlingExample();

echo "Example execution completed.\n";