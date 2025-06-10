<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Providers;

use Illuminate\Support\ServiceProvider;
use LaravelModuleDiscovery\ComposerHook\Commands\ModuleDiscoverCommand;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\NamespaceExtractorInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\PathResolverInterface;
use LaravelModuleDiscovery\ComposerHook\Services\ClassDiscoveryService;
use LaravelModuleDiscovery\ComposerHook\Services\ComposerLoaderService;
use LaravelModuleDiscovery\ComposerHook\Services\ConfigurationService;
use LaravelModuleDiscovery\ComposerHook\Services\NamespaceExtractorService;
use LaravelModuleDiscovery\ComposerHook\Services\PathResolverService;

/**
 * ModuleDiscoveryServiceProvider registers the module discovery services and commands.
 * This service provider binds all interfaces to their concrete implementations
 * and registers the Artisan command for module discovery operations.
 *
 * The provider follows Laravel's service container patterns and ensures
 * proper dependency injection for all module discovery components.
 */
class ModuleDiscoveryServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * When set to true, the provider will only be loaded when
     * one of its services is actually requested.
     */
    protected bool $defer = false;

    /**
     * Register any application services.
     * Binds all interfaces to their concrete implementations in the
     * service container and configures dependency injection mappings.
     */
    public function register(): void
    {
        $this->registerCoreServices();
        $this->registerCommands();
    }

    /**
     * Bootstrap any application services.
     * Performs any initialization that needs to happen after
     * all services are registered and the application is booted.
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->registerConsoleCommands();
    }

    /**
     * Registers the core module discovery services.
     * Binds all service interfaces to their concrete implementations
     * with proper singleton patterns for performance optimization.
     */
    private function registerCoreServices(): void
    {
        // Register ConfigurationInterface first as other services depend on it
        $this->app->singleton(ConfigurationInterface::class, function () {
            return ConfigurationService::make();
        });

        // Register NamespaceExtractorInterface
        $this->app->singleton(NamespaceExtractorInterface::class, function ($app) {
            return NamespaceExtractorService::make(
                $app->make(ConfigurationInterface::class)
            );
        });

        // Register PathResolverInterface
        $this->app->singleton(PathResolverInterface::class, function () {
            return PathResolverService::make();
        });

        // Register ComposerLoaderInterface
        $this->app->singleton(ComposerLoaderInterface::class, function () {
            return ComposerLoaderService::make();
        });

        // Register ClassDiscoveryInterface
        $this->app->singleton(ClassDiscoveryInterface::class, function ($app) {
            return ClassDiscoveryService::make(
                $app->make(NamespaceExtractorInterface::class),
                $app->make(PathResolverInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });
    }

    /**
     * Registers the Artisan commands provided by the package.
     * Binds the module discovery command with proper dependency
     * injection of required service interfaces.
     */
    private function registerCommands(): void
    {
        $this->app->singleton(ModuleDiscoverCommand::class, function ($app) {
            return new ModuleDiscoverCommand(
                $app->make(ClassDiscoveryInterface::class),
                $app->make(ComposerLoaderInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });
    }

    /**
     * Publishes configuration files and other package assets.
     * Makes package configuration and resources available
     * for customization in the host application.
     */
    private function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/module-discovery.php' => config_path('module-discovery.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/module-discovery.php',
            'module-discovery'
        );
    }

    /**
     * Registers console commands with Laravel's Artisan system.
     * Makes the module discovery command available through
     * the Artisan command-line interface.
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleDiscoverCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     * Returns an array of service class names that this provider
     * makes available to the service container.
     *
     * Returns:
     *   - array<string>: Array of provided service class names.
     */
    public function provides(): array
    {
        return [
            ClassDiscoveryInterface::class,
            NamespaceExtractorInterface::class,
            PathResolverInterface::class,
            ComposerLoaderInterface::class,
            ConfigurationInterface::class,
            ModuleDiscoverCommand::class,
        ];
    }
}
