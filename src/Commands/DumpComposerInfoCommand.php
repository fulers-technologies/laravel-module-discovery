<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Commands;

use Illuminate\Console\Command;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * DumpComposerInfoCommand dumps detailed Composer autoloader information.
 * This command provides a comprehensive view of what's registered in
 * Composer's autoloader for debugging and verification purposes.
 */
#[AsCommand(
    name: 'module:dump-composer',
    description: 'Dump detailed Composer autoloader information for debugging',
    aliases: ['dump:composer', 'composer:dump-info']
)]
class DumpComposerInfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'module:dump-composer {--format=table : Output format (table|json|raw)}';

    /**
     * The console command description.
     */
    protected $description = 'Dump detailed Composer autoloader information for debugging';

    /**
     * Creates a new DumpComposerInfoCommand instance.
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
     *
     * Returns:
     *   - int: Command exit code (0 for success, 1 for failure).
     */
    public function handle(): int
    {
        $this->info('📋 Composer Autoloader Information');
        $this->line('');

        try {
            $classLoader = $this->composerLoader->getClassLoader();
            $format = $this->option('format');

            $this->showPsr4Info($classLoader, $format);
            $this->line('');

            $this->showClassMapInfo($classLoader, $format);
            $this->line('');

            $this->showFilesInfo($classLoader);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to dump Composer info: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Shows PSR-4 autoloader information.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     *   - string $format: Output format.
     */
    private function showPsr4Info($classLoader, string $format): void
    {
        $psr4Prefixes = $classLoader->getPrefixesPsr4();

        $this->info("🎯 PSR-4 Namespaces (" . count($psr4Prefixes) . " total)");

        if ($format === 'json') {
            $this->line(json_encode($psr4Prefixes, JSON_PRETTY_PRINT));
            return;
        }

        if ($format === 'raw') {
            foreach ($psr4Prefixes as $namespace => $paths) {
                $this->line("{$namespace} => " . implode(', ', $paths));
            }
            return;
        }

        // Table format
        $tableData = [];
        foreach ($psr4Prefixes as $namespace => $paths) {
            $isModule = str_starts_with($namespace, 'App\\Modules\\') ? '✅' : '';
            $tableData[] = [
                'namespace' => $namespace,
                'paths' => implode("\n", $paths),
                'module' => $isModule,
            ];
        }

        $this->table(['Namespace', 'Paths', 'Module'], $tableData);
    }

    /**
     * Shows class map information.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     *   - string $format: Output format.
     */
    private function showClassMapInfo($classLoader, string $format): void
    {
        $classMap = $classLoader->getClassMap();

        $this->info("📚 Class Map (" . count($classMap) . " entries)");

        if (empty($classMap)) {
            $this->line("   No class map entries found");
            return;
        }

        if ($format === 'json') {
            $this->line(json_encode($classMap, JSON_PRETTY_PRINT));
            return;
        }

        // Show sample entries
        $count = 0;
        $maxShow = 20;

        foreach ($classMap as $className => $filePath) {
            if ($count >= $maxShow) {
                break;
            }

            $this->line("   {$className} => {$filePath}");
            $count++;
        }

        if (count($classMap) > $maxShow) {
            $remaining = count($classMap) - $maxShow;
            $this->line("   ... and {$remaining} more entries");
        }
    }

    /**
     * Shows autoloaded files information.
     *
     * Parameters:
     *   - \Composer\Autoload\ClassLoader $classLoader: The Composer ClassLoader instance.
     */
    private function showFilesInfo($classLoader): void
    {
        $this->info("📁 Autoloaded Files");

        try {
            // Try to find the vendor directory
            $reflection = new \ReflectionClass($classLoader);
            $vendorDir = dirname(dirname($reflection->getFileName()));
            $filesAutoload = $vendorDir . '/autoload_files.php';

            if (file_exists($filesAutoload)) {
                $files = require $filesAutoload;
                $this->line("   Found " . count($files) . " autoloaded files");

                $count = 0;
                foreach ($files as $fileId => $filePath) {
                    if ($count >= 10) {
                        break;
                    }
                    $this->line("   {$filePath}");
                    $count++;
                }

                if (count($files) > 10) {
                    $remaining = count($files) - 10;
                    $this->line("   ... and {$remaining} more files");
                }
            } else {
                $this->line("   No autoloaded files found");
            }
        } catch (\Exception $e) {
            $this->line("   Could not determine autoloaded files: " . $e->getMessage());
        }
    }
}
