<?php

declare (strict_types = 1);

namespace LaravelModuleDiscovery\ComposerHook\Scripts;

use Composer\Script\Event;

/**
 * ComposerHookInstaller handles the installation and setup of Composer hooks.
 * This class provides methods to automatically configure Composer scripts
 * in the host project's composer.json file for seamless integration.
 *
 * The installer ensures that module discovery hooks are properly configured
 * without requiring manual intervention from the user.
 */
class ComposerHookInstaller
{
    /**
     * Installs Composer hooks in the host project.
     * Automatically adds the necessary script entries to composer.json
     * to enable automatic module discovery during Composer operations.
     *
     * Parameters:
     *   - Event $event: The Composer event instance containing context information.
     */
    public static function install(Event $event): void
    {
        $io       = $event->getIO();
        $composer = $event->getComposer();

        try {
            $io->write('🔧 <info>Laravel Module Discovery:</info> Installing Composer hooks...');

            $composerJsonPath = static::getComposerJsonPath();

            if (! file_exists($composerJsonPath)) {
                $io->writeError('❌ <error>Laravel Module Discovery:</error> composer.json not found');
                return;
            }

            $composerData = json_decode(file_get_contents($composerJsonPath), true);

            if (! $composerData) {
                $io->writeError('❌ <error>Laravel Module Discovery:</error> Failed to parse composer.json');
                return;
            }

            $updated = static::addScriptHooks($composerData);

            if ($updated) {
                static::writeComposerJson($composerJsonPath, $composerData);
                $io->write('✅ <info>Laravel Module Discovery:</info> Composer hooks installed successfully');
                $io->write('ℹ️  <comment>Module discovery will now run automatically during:</comment>');
                $io->write('   • composer install');
                $io->write('   • composer update');
                $io->write('   • composer dump-autoload');
            } else {
                $io->write('ℹ️  <comment>Laravel Module Discovery:</comment> Hooks already configured');
            }

        } catch (\Exception $e) {
            $io->writeError("❌ <error>Laravel Module Discovery Error:</error> " . $e->getMessage());
        }
    }

    /**
     * Uninstalls Composer hooks from the host project.
     * Removes the module discovery script entries from composer.json
     * to disable automatic discovery operations.
     *
     * Parameters:
     *   - Event $event: The Composer event instance containing context information.
     */
    public static function uninstall(Event $event): void
    {
        $io = $event->getIO();

        try {
            $io->write('🔧 <info>Laravel Module Discovery:</info> Uninstalling Composer hooks...');

            $composerJsonPath = static::getComposerJsonPath();

            if (! file_exists($composerJsonPath)) {
                $io->writeError('❌ <error>Laravel Module Discovery:</error> composer.json not found');
                return;
            }

            $composerData = json_decode(file_get_contents($composerJsonPath), true);

            if (! $composerData) {
                $io->writeError('❌ <error>Laravel Module Discovery:</error> Failed to parse composer.json');
                return;
            }

            $updated = static::removeScriptHooks($composerData);

            if ($updated) {
                static::writeComposerJson($composerJsonPath, $composerData);
                $io->write('✅ <info>Laravel Module Discovery:</info> Composer hooks uninstalled successfully');
            } else {
                $io->write('ℹ️  <comment>Laravel Module Discovery:</comment> No hooks found to remove');
            }

        } catch (\Exception $e) {
            $io->writeError("❌ <error>Laravel Module Discovery Error:</error> " . $e->getMessage());
        }
    }

    /**
     * Adds script hooks to the composer.json data.
     * Inserts the necessary script entries for automatic module discovery
     * while preserving existing scripts and maintaining proper structure.
     *
     * Parameters:
     *   - array<string, mixed> $composerData: The composer.json data array.
     *
     * Returns:
     *   - bool: True if hooks were added, false if already present.
     */
    private static function addScriptHooks(array &$composerData): bool
    {
        $hooks   = static::getScriptHooks();
        $updated = false;

        // Initialize scripts section if it doesn't exist
        if (! isset($composerData['scripts'])) {
            $composerData['scripts'] = [];
        }

        foreach ($hooks as $event => $script) {
            if (! isset($composerData['scripts'][$event])) {
                $composerData['scripts'][$event] = [];
            }

            // Convert to array if it's a string
            if (is_string($composerData['scripts'][$event])) {
                $composerData['scripts'][$event] = [$composerData['scripts'][$event]];
            }

            // Add our script if not already present
            if (! in_array($script, $composerData['scripts'][$event], true)) {
                $composerData['scripts'][$event][] = $script;
                $updated                           = true;
            }
        }

        return $updated;
    }

    /**
     * Removes script hooks from the composer.json data.
     * Removes the module discovery script entries while preserving
     * other scripts and maintaining proper structure.
     *
     * Parameters:
     *   - array<string, mixed> $composerData: The composer.json data array.
     *
     * Returns:
     *   - bool: True if hooks were removed, false if not found.
     */
    private static function removeScriptHooks(array &$composerData): bool
    {
        $hooks   = static::getScriptHooks();
        $updated = false;

        if (! isset($composerData['scripts'])) {
            return false;
        }

        foreach ($hooks as $event => $script) {
            if (isset($composerData['scripts'][$event])) {
                // Convert to array if it's a string
                if (is_string($composerData['scripts'][$event])) {
                    $composerData['scripts'][$event] = [$composerData['scripts'][$event]];
                }

                // Remove our script
                $key = array_search($script, $composerData['scripts'][$event], true);
                if ($key !== false) {
                    unset($composerData['scripts'][$event][$key]);
                    $composerData['scripts'][$event] = array_values($composerData['scripts'][$event]);
                    $updated                         = true;

                    // Remove empty event arrays
                    if (empty($composerData['scripts'][$event])) {
                        unset($composerData['scripts'][$event]);
                    }
                }
            }
        }

        // Remove empty scripts section
        if (empty($composerData['scripts'])) {
            unset($composerData['scripts']);
        }

        return $updated;
    }

    /**
     * Gets the script hooks configuration.
     * Returns the mapping of Composer events to script commands
     * that should be added for automatic module discovery.
     *
     * Returns:
     *   - array<string, string>: Array of event names to script commands.
     */
    private static function getScriptHooks(): array
    {
        return [
            'post-install-cmd'   => 'LaravelModuleDiscovery\\ComposerHook\\Scripts\\ComposerScripts::postInstall',
            'post-update-cmd'    => 'LaravelModuleDiscovery\\ComposerHook\\Scripts\\ComposerScripts::postUpdate',
            'post-autoload-dump' => 'LaravelModuleDiscovery\\ComposerHook\\Scripts\\ComposerScripts::postAutoloadDump',
        ];
    }

    /**
     * Gets the path to the composer.json file.
     * Determines the location of the composer.json file in the
     * current working directory or project root.
     *
     * Returns:
     *   - string: The absolute path to composer.json.
     */
    private static function getComposerJsonPath(): string
    {
        return getcwd() . '/composer.json';
    }

    /**
     * Writes the composer.json data to file.
     * Saves the updated composer.json data with proper formatting
     * and error handling for file operations.
     *
     * Parameters:
     *   - string $path: The path to the composer.json file.
     *   - array<string, mixed> $data: The composer.json data to write.
     *
     * @throws \Exception When file writing fails.
     */
    private static function writeComposerJson(string $path, array $data): void
    {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonContent === false) {
            throw new \Exception('Failed to encode composer.json data');
        }

        if (file_put_contents($path, $jsonContent) === false) {
            throw new \Exception('Failed to write composer.json file');
        }
    }

    /**
     * Validates the composer.json structure.
     * Checks that the composer.json file has the required structure
     * and can be safely modified for hook installation.
     *
     * Parameters:
     *   - array<string, mixed> $composerData: The composer.json data to validate.
     *
     * Returns:
     *   - bool: True if the structure is valid, false otherwise.
     */
    private static function validateComposerStructure(array $composerData): bool
    {
        // Check for required fields
        $requiredFields = ['name', 'type'];

        foreach ($requiredFields as $field) {
            if (! isset($composerData[$field])) {
                return false;
            }
        }

        // Validate scripts section if it exists
        if (isset($composerData['scripts']) && ! is_array($composerData['scripts'])) {
            return false;
        }

        return true;
    }

    /**
     * Creates a backup of composer.json before modification.
     * Creates a backup copy of the composer.json file to allow
     * recovery in case of modification errors.
     *
     * Parameters:
     *   - string $composerJsonPath: The path to the composer.json file.
     *
     * Returns:
     *   - string: The path to the backup file.
     *
     * @throws \Exception When backup creation fails.
     */
    private static function createBackup(string $composerJsonPath): string
    {
        $backupPath = $composerJsonPath . '.backup.' . date('Y-m-d-H-i-s');

        if (! copy($composerJsonPath, $backupPath)) {
            throw new \Exception('Failed to create backup of composer.json');
        }

        return $backupPath;
    }

    /**
     * Restores composer.json from backup.
     * Restores the composer.json file from a previously created backup
     * in case of modification errors or rollback requirements.
     *
     * Parameters:
     *   - string $backupPath: The path to the backup file.
     *   - string $composerJsonPath: The path to the composer.json file.
     *
     * @throws \Exception When restore operation fails.
     */
    private static function restoreFromBackup(string $backupPath, string $composerJsonPath): void
    {
        if (! file_exists($backupPath)) {
            throw new \Exception('Backup file not found');
        }

        if (! copy($backupPath, $composerJsonPath)) {
            throw new \Exception('Failed to restore composer.json from backup');
        }

        // Clean up backup file
        unlink($backupPath);
    }
}
