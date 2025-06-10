<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Tests;

use Illuminate\Console\Application;
use LaravelModuleDiscovery\ComposerHook\Commands\ModuleDiscoverCommand;
use LaravelModuleDiscovery\ComposerHook\Exceptions\DirectoryNotFoundException;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * ModuleDiscoverCommandTest contains unit tests for the ModuleDiscoverCommand.
 * This test class verifies the functionality of the Artisan command including
 * argument processing, output formatting, error handling, and integration
 * with the discovery and loader services.
 */
class ModuleDiscoverCommandTest extends TestCase
{
    private ClassDiscoveryInterface $mockClassDiscovery;
    private ComposerLoaderInterface $mockComposerLoader;
    private ModuleDiscoverCommand $command;

    /**
     * Sets up the test environment before each test method.
     * Creates mock dependencies and initializes the command instance
     * with controlled behavior for predictable testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClassDiscovery = $this->createMock(ClassDiscoveryInterface::class);
        $this->mockComposerLoader = $this->createMock(ComposerLoaderInterface::class);

        $this->command = new ModuleDiscoverCommand(
            $this->mockClassDiscovery,
            $this->mockComposerLoader
        );
    }

    /**
     * Tests successful command execution with discovered modules.
     * Verifies that the command correctly processes discovered classes,
     * registers namespaces, and displays appropriate success messages.
     */
    public function testSuccessfulModuleDiscovery(): void
    {
        $discoveredClasses = [
            'App\\Modules\\Blog\\Controllers' => '/path/to/blog/controllers',
            'App\\Modules\\User\\Services' => '/path/to/user/services',
        ];

        $registrationResults = [
            'App\\Modules\\Blog\\Controllers' => true,
            'App\\Modules\\User\\Services' => true,
        ];

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willReturn($discoveredClasses);

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('getDiscoveryStatus')
            ->willReturn([
                'status' => 'completed',
                'statistics' => [
                    'processed_files' => 10,
                    'processing_time' => 0.5,
                    'error_files' => [],
                ],
            ]);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('registerMultipleNamespaces')
            ->with($discoveredClasses)
            ->willReturn($registrationResults);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('applyRegistrations')
            ->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Module discovery completed successfully!', $commandTester->getDisplay());
        $this->assertStringContainsString('Discovered 2 namespaces, registered 2 successfully', $commandTester->getDisplay());
    }

    /**
     * Tests command execution when no modules are found.
     * Verifies that the command handles empty discovery results gracefully
     * and displays appropriate warning messages.
     */
    public function testNoModulesFound(): void
    {
        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willReturn([]);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No modules found in the specified directory', $commandTester->getDisplay());
    }

    /**
     * Tests command execution with directory not found error.
     * Verifies that the command properly handles missing directory exceptions
     * and displays helpful error messages with suggestions.
     */
    public function testDirectoryNotFound(): void
    {
        $exception = DirectoryNotFoundException::modulesDirectoryMissing(
            '/non/existent/path',
            '/base/path'
        );

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willThrowException($exception);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('does not exist', $commandTester->getDisplay());
        $this->assertStringContainsString('Suggested directories:', $commandTester->getDisplay());
    }

    /**
     * Tests command execution with partial registration failures.
     * Verifies that the command handles scenarios where some namespace
     * registrations succeed while others fail.
     */
    public function testPartialRegistrationFailure(): void
    {
        $discoveredClasses = [
            'App\\Modules\\Blog\\Controllers' => '/path/to/blog/controllers',
            'App\\Modules\\User\\Services' => '/path/to/user/services',
        ];

        $registrationResults = [
            'App\\Modules\\Blog\\Controllers' => true,
            'App\\Modules\\User\\Services' => false, // This one failed
        ];

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willReturn($discoveredClasses);

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('getDiscoveryStatus')
            ->willReturn([
                'status' => 'completed',
                'statistics' => [],
            ]);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('registerMultipleNamespaces')
            ->willReturn($registrationResults);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('applyRegistrations')
            ->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode); // Should fail due to partial failure
        $this->assertStringContainsString('Discovered 2 namespaces, registered 1 successfully', $commandTester->getDisplay());
    }

    /**
     * Tests command execution with verbose output flag.
     * Verifies that the command displays detailed information when
     * the verbose flag is provided.
     */
    public function testVerboseOutput(): void
    {
        $discoveredClasses = [
            'App\\Modules\\Test\\Controllers' => '/path/to/test',
        ];

        $registrationResults = [
            'App\\Modules\\Test\\Controllers' => true,
        ];

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willReturn($discoveredClasses);

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('getDiscoveryStatus')
            ->willReturn([
                'status' => 'completed',
                'statistics' => [
                    'processed_files' => 5,
                    'processing_time' => 0.25,
                    'error_files' => [],
                ],
            ]);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('registerMultipleNamespaces')
            ->willReturn($registrationResults);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('applyRegistrations')
            ->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--verbose' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        
        $this->assertStringContainsString('Registered namespaces:', $output);
        $this->assertStringContainsString('Discovery Statistics:', $output);
        $this->assertStringContainsString('Processed files: 5', $output);
        $this->assertStringContainsString('Processing time:', $output);
    }

    /**
     * Tests command execution with custom path option.
     * Verifies that the command respects the custom path option
     * and uses it for module discovery operations.
     */
    public function testCustomPathOption(): void
    {
        $customPath = '/custom/modules/path';
        
        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->with($this->callback(function ($path) use ($customPath) {
                return str_contains($path, 'custom/modules/path');
            }))
            ->willReturn([]);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--path' => $customPath]);

        $this->assertEquals(0, $exitCode);
    }

    /**
     * Tests command execution with application registration failure.
     * Verifies that the command handles scenarios where namespace
     * registration succeeds but application fails.
     */
    public function testApplicationRegistrationFailure(): void
    {
        $discoveredClasses = [
            'App\\Modules\\Test\\Controllers' => '/path/to/test',
        ];

        $registrationResults = [
            'App\\Modules\\Test\\Controllers' => true,
        ];

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willReturn($discoveredClasses);

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('getDiscoveryStatus')
            ->willReturn([
                'status' => 'completed',
                'statistics' => [],
            ]);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('registerMultipleNamespaces')
            ->willReturn($registrationResults);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('applyRegistrations')
            ->willReturn(false); // Application fails

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Warning: Some registrations may not be active', $commandTester->getDisplay());
    }

    /**
     * Tests command execution with discovery statistics containing errors.
     * Verifies that the command properly displays error information
     * when files cannot be processed during discovery.
     */
    public function testDiscoveryWithFileErrors(): void
    {
        $discoveredClasses = [
            'App\\Modules\\Valid\\Controllers' => '/path/to/valid',
        ];

        $registrationResults = [
            'App\\Modules\\Valid\\Controllers' => true,
        ];

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('discoverClasses')
            ->willReturn($discoveredClasses);

        $this->mockClassDiscovery
            ->expects($this->once())
            ->method('getDiscoveryStatus')
            ->willReturn([
                'status' => 'completed',
                'statistics' => [
                    'processed_files' => 8,
                    'processing_time' => 0.3,
                    'error_files' => [
                        ['file' => '/path/to/error.php', 'error' => 'Syntax error'],
                    ],
                ],
            ]);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('registerMultipleNamespaces')
            ->willReturn($registrationResults);

        $this->mockComposerLoader
            ->expects($this->once())
            ->method('applyRegistrations')
            ->willReturn(true);

        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--verbose' => true]);

        $this->assertEquals(0, $exitCode);
        $output = $commandTester->getDisplay();
        
        $this->assertStringContainsString('Files with errors: 1', $output);
        $this->assertStringContainsString('/path/to/error.php: Syntax error', $output);
    }
}