<?php

declare(strict_types=1);

namespace LaravelModuleDiscovery\ComposerHook\Providers;

use Illuminate\Support\ServiceProvider;
use LaravelModuleDiscovery\ComposerHook\Commands\ModuleDiscoverCommand;
use LaravelModuleDiscovery\ComposerHook\Commands\AttributeDiscoverCommand;
use LaravelModuleDiscovery\ComposerHook\Commands\VerifyNamespacesCommand;
use LaravelModuleDiscovery\ComposerHook\Commands\InspectAutoloaderCommand;
use LaravelModuleDiscovery\ComposerHook\Commands\TestAutoloadingCommand;
use LaravelModuleDiscovery\ComposerHook\Commands\DumpComposerInfoCommand;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ClassDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ComposerLoaderInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\ConfigurationInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\NamespaceExtractorInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\PathResolverInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeDiscoveryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeRegistryInterface;
use LaravelModuleDiscovery\ComposerHook\Interfaces\AttributeStorageInterface;
use LaravelModuleDiscovery\ComposerHook\Services\ClassDiscoveryService;
use LaravelModuleDiscovery\ComposerHook\Services\ComposerLoaderService;
use LaravelModuleDiscovery\ComposerHook\Services\ConfigurationService;
use LaravelModuleDiscovery\ComposerHook\Services\NamespaceExtractorService;
use LaravelModuleDiscovery\ComposerHook\Services\PathResolverService;
use LaravelModuleDiscovery\ComposerHook\Services\AttributeDiscoveryService;
use LaravelModuleDiscovery\ComposerHook\Services\AttributeRegistryService;
use LaravelModuleDiscovery\ComposerHook\Services\AttributeFileStorageService;
use LaravelModuleDiscovery\ComposerHook\Services\ComposerEventService;

/**
 * ModuleDiscoveryServiceProvider registers the module discovery services and commands.
 * This service provider binds all interfaces to their concrete implementations
 * and registers the Artisan commands for module discovery operations.
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
        $this->registerAttributeServices();
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

        // Register ComposerEventService
        $this->app->singleton(ComposerEventService::class, function ($app) {
            return ComposerEventService::make(
                $app->make(ConfigurationInterface::class)
            );
        });
    }

    /**
     * Registers the attribute discovery services.
     * Binds attribute-related interfaces to their concrete implementations
     * with proper dependency injection for attribute discovery functionality.
     */
    private function registerAttributeServices(): void
    {
        // Register AttributeStorageInterface
        $this->app->singleton(AttributeStorageInterface::class, function ($app) {
            return AttributeFileStorageService::make(
                $app->make(ConfigurationInterface::class)
            );
        });

        // Register AttributeRegistryInterface
        $this->app->singleton(AttributeRegistryInterface::class, function ($app) {
            return AttributeRegistryService::make(
                $app->make(AttributeStorageInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });

        // Register AttributeDiscoveryInterface
        $this->app->singleton(AttributeDiscoveryInterface::class, function ($app) {
            return AttributeDiscoveryService::make(
                $app->make(ClassDiscoveryInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });
    }

    /**
     * Registers the Artisan commands provided by the package.
     * Binds all module discovery commands with proper dependency
     * injection of required service interfaces.
     */
    private function registerCommands(): void
    {
        // Module Discovery Command
        $this->app->singleton(ModuleDiscoverCommand::class, function ($app) {
            return new ModuleDiscoverCommand(
                $app->make(ClassDiscoveryInterface::class),
                $app->make(ComposerLoaderInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });

        // Attribute Discovery Command
        $this->app->singleton(AttributeDiscoverCommand::class, function ($app) {
            return new AttributeDiscoverCommand(
                $app->make(AttributeDiscoveryInterface::class),
                $app->make(AttributeRegistryInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });

        // Namespace Verification Command
        $this->app->singleton(VerifyNamespacesCommand::class, function ($app) {
            return new VerifyNamespacesCommand(
                $app->make(ComposerLoaderInterface::class),
                $app->make(ConfigurationInterface::class)
            );
        });

        // Autoloader Inspection Command
        $this->app->singleton(InspectAutoloaderCommand::class, function ($app) {
            return new InspectAutoloaderCommand(
                $app->make(ComposerLoaderInterface::class)
            );
        });

        // Autoloading Test Command
        $this->app->singleton(TestAutoloadingCommand::class, function ($app) {
            return new TestAutoloadingCommand(
                $app->make(ComposerLoaderInterface::class)
            );
        });

        // Composer Info Dump Command
        $this->app->singleton(DumpComposerInfoCommand::class, function ($app) {
            return new DumpComposerInfoCommand(
                $app->make(ComposerLoaderInterface::class)
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
        // Publish module discovery configuration
        $this->publishes([
            __DIR__ . '/../../config/module-discovery.php' => config_path('module-discovery.php'),
        ], 'module-discovery-config');

        // Publish attribute discovery configuration
        $this->publishes([
            __DIR__ . '/../../config/attribute-discovery.php' => config_path('attribute-discovery.php'),
        ], 'attribute-discovery-config');

        // Publish all configurations together
        $this->publishes([
            __DIR__ . '/../../config/module-discovery.php' => config_path('module-discovery.php'),
            __DIR__ . '/../../config/attribute-discovery.php' => config_path('attribute-discovery.php'),
        ], 'laravel-module-discovery-config');

        // Merge configurations from package
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/module-discovery.php',
            'module-discovery'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/attribute-discovery.php',
            'attribute-discovery'
        );
    }

    /**
     * Registers console commands with Laravel's Artisan system.
     * Makes all module discovery commands available through
     * the Artisan command-line interface.
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleDiscoverCommand::class,
                AttributeDiscoverCommand::class,
                VerifyNamespacesCommand::class,
                InspectAutoloaderCommand::class,
                TestAutoloadingCommand::class,
                DumpComposerInfoCommand::class,
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
            // Core Services
            ClassDiscoveryInterface::class,
            NamespaceExtractorInterface::class,
            PathResolverInterface::class,
            ComposerLoaderInterface::class,
            ConfigurationInterface::class,
            ComposerEventService::class,

            // Attribute Services
            AttributeDiscoveryInterface::class,
            AttributeRegistryInterface::class,
            AttributeStorageInterface::class,

            // Commands
            ModuleDiscoverCommand::class,
            AttributeDiscoverCommand::class,
            VerifyNamespacesCommand::class,
            InspectAutoloaderCommand::class,
            TestAutoloadingCommand::class,
            DumpComposerInfoCommand::class,
        ];
    }
}
