<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Tests;

use LaravelModuleDiscovery\ComposerHook\Enums\DiscoveryStatusEnum;
use LaravelModuleDiscovery\ComposerHook\Exceptions\DirectoryNotFoundException;
use LaravelModuleDiscovery\ComposerHook\Exceptions\ModuleDiscoveryException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\NamespaceExtractorInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\PathResolverInterface;
use LaravelModuleDiscovery\ComposerHook\Services\ClassDiscoveryService;
use PHPUnit\Framework\TestCase;

/**
 * ClassDiscoveryServiceTest contains unit tests for the ClassDiscoveryService.
 * This test class verifies the functionality of class discovery operations,
 * directory validation, status tracking, and error handling scenarios.
 *
 * The tests ensure that the service properly integrates with its dependencies
 * and handles various edge cases during the discovery process.
 */
class ClassDiscoveryServiceTest extends TestCase
{
    private ClassDiscoveryService $classDiscoveryService;
    private NamespaceExtractorInterface $mockNamespaceExtractor;
    private PathResolverInterface $mockPathResolver;

    /**
     * Sets up the test environment before each test method.
     * Creates mock dependencies and initializes the service instance
     * with controlled behavior for predictable testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockNamespaceExtractor = $this->createMock(NamespaceExtractorInterface::class);
        $this->mockPathResolver = $this->createMock(PathResolverInterface::class);

        $this->classDiscoveryService = new ClassDiscoveryService(
            $this->mockNamespaceExtractor,
            $this->mockPathResolver
        );
    }

    /**
     * Tests successful class discovery with valid directory and PHP files.
     * Verifies that the service correctly processes PHP files, extracts namespaces,
     * and returns the expected namespace-to-path mappings.
     */
    public function testDiscoverClassesWithValidDirectory(): void
    {
        // Create a temporary directory structure for testing
        $testDir = $this->createTestDirectory();
        $this->createTestPhpFile($testDir, 'TestClass.php', 'App\\Test');

        $this->mockNamespaceExtractor
            ->expects($this->once())
            ->method('extractNamespace')
            ->willReturn('App\\Test');

        $this->mockPathResolver
            ->expects($this->once())
            ->method('getDirectoryPath')
            ->willReturn($testDir);

        $result = $this->classDiscoveryService->discoverClasses($testDir);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('App\\Test', $result);
        $this->assertEquals($testDir, $result['App\\Test']);

        // Verify status is completed
        $status = $this->classDiscoveryService->getDiscoveryStatus();
        $this->assertEquals(DiscoveryStatusEnum::COMPLETED->value, $status['status']);

        $this->cleanupTestDirectory($testDir);
    }

    /**
     * Tests class discovery with an invalid directory path.
     * Verifies that the service throws appropriate exceptions when
     * attempting to scan non-existent directories.
     */
    public function testDiscoverClassesWithInvalidDirectory(): void
    {
        $invalidDir = '/non/existent/directory';

        $this->expectException(DirectoryNotFoundException::class);
        $this->expectExceptionMessage("Modules directory '{$invalidDir}' does not exist");

        $this->classDiscoveryService->discoverClasses($invalidDir);
    }

    /**
     * Tests directory validation with existing and non-existing paths.
     * Verifies that the validateDirectory method correctly identifies
     * valid and invalid directory paths.
     */
    public function testValidateDirectory(): void
    {
        $testDir = $this->createTestDirectory();

        $this->assertTrue($this->classDiscoveryService->validateDirectory($testDir));
        $this->assertFalse($this->classDiscoveryService->validateDirectory('/non/existent/path'));

        $this->cleanupTestDirectory($testDir);
    }

    /**
     * Tests discovery status tracking throughout the process.
     * Verifies that the service correctly updates and reports
     * status information during discovery operations.
     */
    public function testDiscoveryStatusTracking(): void
    {
        // Initial status should be initialized
        $status = $this->classDiscoveryService->getDiscoveryStatus();
        $this->assertEquals(DiscoveryStatusEnum::INITIALIZED->value, $status['status']);
        $this->assertFalse($status['is_terminal']);

        // Test with valid directory
        $testDir = $this->createTestDirectory();
        $this->createTestPhpFile($testDir, 'TestClass.php', 'App\\Test');

        $this->mockNamespaceExtractor
            ->method('extractNamespace')
            ->willReturn('App\\Test');

        $this->mockPathResolver
            ->method('getDirectoryPath')
            ->willReturn($testDir);

        $this->classDiscoveryService->discoverClasses($testDir);

        // Status should be completed
        $status = $this->classDiscoveryService->getDiscoveryStatus();
        $this->assertEquals(DiscoveryStatusEnum::COMPLETED->value, $status['status']);
        $this->assertTrue($status['is_terminal']);
        $this->assertArrayHasKey('statistics', $status);

        $this->cleanupTestDirectory($testDir);
    }

    /**
     * Tests error handling during namespace extraction.
     * Verifies that the service properly handles exceptions from
     * the namespace extractor and continues processing other files.
     */
    public function testErrorHandlingDuringNamespaceExtraction(): void
    {
        $testDir = $this->createTestDirectory();
        $this->createTestPhpFile($testDir, 'ErrorFile.php', '');

        $this->mockNamespaceExtractor
            ->expects($this->once())
            ->method('extractNamespace')
            ->willThrowException(new \Exception('Extraction failed'));

        $result = $this->classDiscoveryService->discoverClasses($testDir);

        // Should return empty array but not throw exception
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // Check that error was recorded in statistics
        $status = $this->classDiscoveryService->getDiscoveryStatus();
        $this->assertArrayHasKey('statistics', $status);
        $this->assertArrayHasKey('error_files', $status['statistics']);
        $this->assertNotEmpty($status['statistics']['error_files']);

        $this->cleanupTestDirectory($testDir);
    }

    /**
     * Tests the static make factory method.
     * Verifies that the factory method creates a properly configured
     * service instance with default dependencies.
     */
    public function testMakeFactoryMethod(): void
    {
        $service = ClassDiscoveryService::make();

        $this->assertInstanceOf(ClassDiscoveryService::class, $service);

        // Verify initial status
        $status = $service->getDiscoveryStatus();
        $this->assertEquals(DiscoveryStatusEnum::INITIALIZED->value, $status['status']);
    }

    /**
     * Tests discovery with mixed file types (PHP and non-PHP).
     * Verifies that the service only processes PHP files and ignores
     * other file types during directory scanning.
     */
    public function testDiscoveryWithMixedFileTypes(): void
    {
        $testDir = $this->createTestDirectory();
        
        // Create PHP file that should be processed
        $this->createTestPhpFile($testDir, 'ValidClass.php', 'App\\Valid');
        
        // Create non-PHP files that should be ignored
        file_put_contents($testDir . '/readme.txt', 'This is a text file');
        file_put_contents($testDir . '/config.json', '{"key": "value"}');

        $this->mockNamespaceExtractor
            ->expects($this->once()) // Should only be called once for the PHP file
            ->method('extractNamespace')
            ->willReturn('App\\Valid');

        $this->mockPathResolver
            ->expects($this->once())
            ->method('getDirectoryPath')
            ->willReturn($testDir);

        $result = $this->classDiscoveryService->discoverClasses($testDir);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('App\\Valid', $result);

        $this->cleanupTestDirectory($testDir);
    }

    /**
     * Creates a temporary directory for testing purposes.
     * Returns the path to a newly created temporary directory
     * that can be used for test file operations.
     *
     * Returns:
     *   - string: Path to the created temporary directory.
     */
    private function createTestDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/module_discovery_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        return $tempDir;
    }

    /**
     * Creates a test PHP file with the specified namespace.
     * Generates a PHP file with proper namespace declaration
     * for use in discovery testing scenarios.
     *
     * Parameters:
     *   - string $directory: The directory where the file should be created.
     *   - string $filename: The name of the PHP file to create.
     *   - string $namespace: The namespace to include in the file.
     */
    private function createTestPhpFile(string $directory, string $filename, string $namespace): void
    {
        $content = "<?php\n\nnamespace {$namespace};\n\nclass TestClass\n{\n    // Test class\n}\n";
        file_put_contents($directory . '/' . $filename, $content);
    }

    /**
     * Cleans up the test directory and its contents.
     * Removes all files and directories created during testing
     * to maintain a clean test environment.
     *
     * Parameters:
     *   - string $directory: The directory to clean up.
     */
    private function cleanupTestDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $files = array_diff(scandir($directory), ['.', '..']);
            foreach ($files as $file) {
                unlink($directory . '/' . $file);
            }
            rmdir($directory);
        }
    }
}