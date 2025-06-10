<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;

/**
 * InspectAutoloaderCommand provides detailed inspection of Composer's autoloader.
 * This command shows all registered PSR-4 namespaces, class maps, and autoloader
 * configuration to help debug autoloading issues.
 *
 * The command provides comprehensive information about the current state
 * of Composer's autoloader for troubleshooting purposes.
 */
class InspectAutoloaderCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Defines the command signature including the command name
     * and any optional parameters or flags.
     */
    protected $signature = 'module:inspect-autoloader {--filter= : Filter namespaces by pattern} {--format=table : Output format (table|json)}';

    /**
     * The console command description.
     * Provides a brief description of what the command does
     * for display in the Artisan command list.
     */
    protected $description = 'Inspect Composer autoloader configuration and registered namespaces';

    /**
     * Creates a new InspectAutoloaderCommand instance.
     * Initializes the command with required dependencies for Composer
     * autoloader inspection and analysis.
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
     * Performs comprehensive inspection of the Composer autoloader
     * including PSR-4 mappings, class maps, and configuration.
     *
     * Returns:
     *   - int: Command exit code (0 for success, 1 for failure).
     */
    public function handle(): int
    {
        $this->info('🔍 Inspecting Composer Autoloader Configuration');
        $this->line('');

        try {
            $classLoader = $this->composerLoader->getClassLoader();
            $filter = $this->option('filter');
            $format = $this->option('format');

            $this->showAutoloaderInfo($classLoader);
            $this->line('');

            $this->showPsr4Namespaces($classLoader, $filter, $format);
            $this->line('');

            $this->showClassMap($classLoader, $filter);
            $this->line('');

            $this->showFiles($classLoader);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to inspect autoloader: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Shows general autoloader information.
     * Displays basic information about the Composer ClassLoader instance
     * and its configuration.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     */
    private function showAutoloaderInfo($classLoader): void
    {
        $this->info('📋 Autoloader Information:');

        $reflection = new \ReflectionClass($classLoader);
        $this->line("   Class: " . $reflection->getName());
        $this->line("   File: " . $reflection->getFileName());

        // Try to get some basic stats
        $psr4Count = count($classLoader->getPrefixesPsr4());
        $classMapCount = count($classLoader->getClassMap());

        $this->line("   PSR-4 Namespaces: {$psr4Count}");
        $this->line("   Class Map Entries: {$classMapCount}");
    }

    /**
     * Shows PSR-4 namespace mappings.
     * Displays all registered PSR-4 namespaces with their associated paths
     * in the specified format.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     *   - string|null $filter: Optional filter pattern for namespaces.
     *   - string $format: Output format (table or json).
     */
    private function showPsr4Namespaces($classLoader, ?string $filter, string $format): void
    {
        $psr4Prefixes = $classLoader->getPrefixesPsr4();

        if ($filter) {
            $psr4Prefixes = array_filter($psr4Prefixes, function($namespace) use ($filter) {
                return str_contains($namespace, $filter);
            }, ARRAY_FILTER_USE_KEY);
        }

        $this->info("🎯 PSR-4 Namespaces (" . count($psr4Prefixes) . " total):");

        if (empty($psr4Prefixes)) {
            $this->line("   No PSR-4 namespaces found" . ($filter ? " matching '{$filter}'" : ""));
            return;
        }

        if ($format === 'json') {
            $this->line(json_encode($psr4Prefixes, JSON_PRETTY_PRINT));
            return;
        }

        $tableData = [];
        foreach ($psr4Prefixes as $namespace => $paths) {
            $pathsStr = implode("\n", $paths);
            $tableData[] = [
                'namespace' => $namespace,
                'paths' => $pathsStr,
                'count' => count($paths),
            ];
        }

        $this->table(['Namespace', 'Paths', 'Path Count'], $tableData);
    }

    /**
     * Shows class map entries.
     * Displays registered class map entries with optional filtering
     * for debugging specific class loading issues.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     *   - string|null $filter: Optional filter pattern for class names.
     */
    private function showClassMap($classLoader, ?string $filter): void
    {
        $classMap = $classLoader->getClassMap();

        if ($filter) {
            $classMap = array_filter($classMap, function($className) use ($filter) {
                return str_contains($className, $filter);
            }, ARRAY_FILTER_USE_KEY);
        }

        $this->info("📚 Class Map (" . count($classMap) . " entries):");

        if (empty($classMap)) {
            $this->line("   No class map entries found" . ($filter ? " matching '{$filter}'" : ""));
            return;
        }

        // Show first 10 entries to avoid overwhelming output
        $displayCount = min(10, count($classMap));
        $this->line("   Showing first {$displayCount} entries:");

        $count = 0;
        foreach ($classMap as $className => $filePath) {
            if ($count >= $displayCount) {
                break;
            }

            $this->line("   {$className} => {$filePath}");
            $count++;
        }

        if (count($classMap) > $displayCount) {
            $remaining = count($classMap) - $displayCount;
            $this->line("   ... and {$remaining} more entries");
        }
    }

    /**
     * Shows autoloaded files.
     * Displays files that are automatically included by Composer
     * for functions and global definitions.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     */
    private function showFiles($classLoader): void
    {
        // Try to get files information if available
        $this->info("📁 Autoloaded Files:");

        try {
            // This is a bit tricky as files are usually loaded via a separate mechanism
            // We'll check for the common files autoload pattern
            $vendorDir = dirname(dirname($classLoader->findFile('Composer\\Autoload\\ClassLoader')));
            $filesAutoload = $vendorDir . '/composer/autoload_files.php';

            if (file_exists($filesAutoload)) {
                $files = require $filesAutoload;
                $this->line("   Found " . count($files) . " autoloaded files");

                // Show first few files
                $count = 0;
                foreach ($files as $fileId => $filePath) {
                    if ($count >= 5) {
                        break;
                    }
                    $this->line("   {$filePath}");
                    $count++;
                }

                if (count($files) > 5) {
                    $remaining = count($files) - 5;
                    $this->line("   ... and {$remaining} more files");
                }
            } else {
                $this->line("   No autoloaded files found");
            }
        } catch (\Exception $e) {
            $this->line("   Could not determine autoloaded files");
        }
    }
}
