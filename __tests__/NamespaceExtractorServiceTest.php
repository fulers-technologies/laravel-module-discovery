<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Tests;

use LaravelModuleDiscovery\ComposerHook\Exceptions\NamespaceExtractionException;
use LaravelModuleDiscovery\ComposerHook\Services\NamespaceExtractorService;
use PHPUnit\Framework\TestCase;

/**
 * NamespaceExtractorServiceTest contains unit tests for the NamespaceExtractorService.
 * This test class verifies the functionality of namespace extraction from PHP files,
 * token parsing, namespace validation, and error handling scenarios.
 *
 * The tests ensure that the service correctly identifies and extracts namespaces
 * from various PHP file formats and syntax patterns.
 */
class NamespaceExtractorServiceTest extends TestCase
{
    private NamespaceExtractorService $namespaceExtractor;

    /**
     * Sets up the test environment before each test method.
     * Creates a fresh instance of the namespace extractor service
     * for isolated testing of each method.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->namespaceExtractor = NamespaceExtractorService::make();
    }

    /**
     * Tests successful namespace extraction from a valid PHP file.
     * Verifies that the service correctly identifies and extracts
     * namespace declarations from properly formatted PHP files.
     */
    public function testExtractNamespaceFromValidFile(): void
    {
        $testFile = $this->createTestPhpFile('App\\Test\\ExampleClass', 'ExampleClass');
        
        $result = $this->namespaceExtractor->extractNamespace($testFile);
        
        $this->assertEquals('App\\Test\\ExampleClass', $result);
        
        unlink($testFile);
    }

    /**
     * Tests namespace extraction from a file without namespace declaration.
     * Verifies that the service returns null when processing PHP files
     * that do not contain namespace declarations.
     */
    public function testExtractNamespaceFromFileWithoutNamespace(): void
    {
        $testFile = $this->createTestPhpFileWithoutNamespace();
        
        $result = $this->namespaceExtractor->extractNamespace($testFile);
        
        $this->assertNull($result);
        
        unlink($testFile);
    }

    /**
     * Tests namespace extraction from a non-existent file.
     * Verifies that the service throws appropriate exceptions when
     * attempting to process files that do not exist.
     */
    public function testExtractNamespaceFromNonExistentFile(): void
    {
        $nonExistentFile = '/tmp/non_existent_file.php';
        
        $this->expectException(NamespaceExtractionException::class);
        $this->expectExceptionMessage("Cannot read file '{$nonExistentFile}' for namespace extraction");
        
        $this->namespaceExtractor->extractNamespace($nonExistentFile);
    }

    /**
     * Tests token parsing for namespace identification.
     * Verifies that the service correctly processes PHP token arrays
     * to identify and extract namespace declarations.
     */
    public function testParseTokensForNamespace(): void
    {
        $phpCode = "<?php\n\nnamespace App\\Services;\n\nclass TestService\n{\n}\n";
        $tokens = token_get_all($phpCode);
        
        $result = $this->namespaceExtractor->parseTokensForNamespace($tokens);
        
        $this->assertEquals('App\\Services', $result);
    }

    /**
     * Tests token parsing with no namespace present.
     * Verifies that the service returns null when processing token arrays
     * that do not contain namespace declarations.
     */
    public function testParseTokensForNamespaceWithoutNamespace(): void
    {
        $phpCode = "<?php\n\nclass TestClass\n{\n}\n";
        $tokens = token_get_all($phpCode);
        
        $result = $this->namespaceExtractor->parseTokensForNamespace($tokens);
        
        $this->assertNull($result);
    }

    /**
     * Tests namespace validation with valid namespace strings.
     * Verifies that the service correctly identifies valid PHP namespace
     * formats according to PSR-4 standards.
     */
    public function testValidateNamespaceWithValidNamespaces(): void
    {
        $validNamespaces = [
            'App',
            'App\\Services',
            'App\\Http\\Controllers',
            'MyPackage\\Features\\Authentication',
            'Vendor\\Package\\SubPackage',
        ];
        
        foreach ($validNamespaces as $namespace) {
            $this->assertTrue(
                $this->namespaceExtractor->validateNamespace($namespace),
                "Failed to validate namespace: {$namespace}"
            );
        }
    }

    /**
     * Tests namespace validation with invalid namespace strings.
     * Verifies that the service correctly rejects namespace strings
     * that do not conform to PHP naming conventions.
     */
    public function testValidateNamespaceWithInvalidNamespaces(): void
    {
        $invalidNamespaces = [
            '',
            '123Invalid',
            'App\\\\Double\\Slash',
            'App\\',
            '\\App',
            'App\\123Number',
            'App\\Invalid-Dash',
            'App\\Invalid Space',
        ];
        
        foreach ($invalidNamespaces as $namespace) {
            $this->assertFalse(
                $this->namespaceExtractor->validateNamespace($namespace),
                "Incorrectly validated invalid namespace: {$namespace}"
            );
        }
    }

    /**
     * Tests namespace extraction from files with complex namespace patterns.
     * Verifies that the service handles various namespace declaration styles
     * including those with comments and different formatting.
     */
    public function testExtractNamespaceFromComplexFiles(): void
    {
        // Test with comments and whitespace
        $testFile = $this->createComplexTestPhpFile();
        
        $result = $this->namespaceExtractor->extractNamespace($testFile);
        
        $this->assertEquals('Complex\\Namespace\\With\\Comments', $result);
        
        unlink($testFile);
    }

    /**
     * Tests caching functionality of the namespace extractor.
     * Verifies that the service caches extraction results to improve
     * performance when processing the same files multiple times.
     */
    public function testNamespaceCaching(): void
    {
        $testFile = $this->createTestPhpFile('App\\Cached\\Namespace', 'CachedClass');
        
        // First extraction
        $result1 = $this->namespaceExtractor->extractNamespace($testFile);
        $this->assertEquals('App\\Cached\\Namespace', $result1);
        
        // Second extraction should use cache
        $result2 = $this->namespaceExtractor->extractNamespace($testFile);
        $this->assertEquals('App\\Cached\\Namespace', $result2);
        
        // Verify cache statistics
        $cacheStats = $this->namespaceExtractor->getCacheStats();
        $this->assertGreaterThan(0, $cacheStats['cache_size']);
        $this->assertContains($testFile, $cacheStats['cached_files']);
        
        unlink($testFile);
    }

    /**
     * Tests cache clearing functionality.
     * Verifies that the service can clear its internal cache
     * and reset extraction state.
     */
    public function testCacheClearance(): void
    {
        $testFile = $this->createTestPhpFile('App\\Test\\Clear', 'ClearTest');
        
        // Extract namespace to populate cache
        $this->namespaceExtractor->extractNamespace($testFile);
        
        // Verify cache is populated
        $cacheStats = $this->namespaceExtractor->getCacheStats();
        $this->assertGreaterThan(0, $cacheStats['cache_size']);
        
        // Clear cache
        $this->namespaceExtractor->clearCache();
        
        // Verify cache is empty
        $cacheStats = $this->namespaceExtractor->getCacheStats();
        $this->assertEquals(0, $cacheStats['cache_size']);
        
        unlink($testFile);
    }

    /**
     * Tests the static make factory method.
     * Verifies that the factory method creates a properly configured
     * service instance ready for namespace extraction operations.
     */
    public function testMakeFactoryMethod(): void
    {
        $extractor = NamespaceExtractorService::make();
        
        $this->assertInstanceOf(NamespaceExtractorService::class, $extractor);
        
        // Verify it can perform basic operations
        $cacheStats = $extractor->getCacheStats();
        $this->assertEquals(0, $cacheStats['cache_size']);
    }

    /**
     * Creates a test PHP file with the specified namespace and class.
     * Generates a temporary PHP file with proper structure for testing
     * namespace extraction functionality.
     *
     * Parameters:
     *   - string $namespace: The namespace to include in the file.
     *   - string $className: The class name to include in the file.
     *
     * Returns:
     *   - string: Path to the created test file.
     */
    private function createTestPhpFile(string $namespace, string $className): string
    {
        $content = "<?php\n\nnamespace {$namespace};\n\nclass {$className}\n{\n    // Test class\n}\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'namespace_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Creates a test PHP file without namespace declaration.
     * Generates a temporary PHP file that contains only class definition
     * without namespace for testing null return scenarios.
     *
     * Returns:
     *   - string: Path to the created test file.
     */
    private function createTestPhpFileWithoutNamespace(): string
    {
        $content = "<?php\n\nclass SimpleClass\n{\n    // Simple class without namespace\n}\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'no_namespace_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Creates a complex test PHP file with comments and formatting.
     * Generates a PHP file with various formatting patterns to test
     * the robustness of namespace extraction.
     *
     * Returns:
     *   - string: Path to the created complex test file.
     */
    private function createComplexTestPhpFile(): string
    {
        $content = "<?php\n\n// This is a comment\n/* Multi-line\n   comment */\n\nnamespace Complex\\Namespace\\With\\Comments;\n\n// Another comment\n\nclass ComplexClass\n{\n    // Complex class\n}\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'complex_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }
}