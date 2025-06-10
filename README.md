# Laravel Composer Hook for Automatic Class Discovery

A comprehensive Laravel package that provides automatic class discovery and namespace registration with Composer's autoloader. This package eliminates the need for manual namespace definitions in `composer.json` by automatically scanning directories, extracting namespaces, and registering them with Composer during install, update, and autoload operations.

## Features

- **Automatic Class Discovery**: Scans directories for PHP files and extracts namespace information
- **Composer Integration**: Seamlessly integrates with Composer's autoloader system
- **PSR-4 Compliance**: Follows PSR-4 autoloading standards for namespace registration
- **Laravel Integration**: Provides Artisan commands and service provider integration
- **Error Handling**: Comprehensive exception handling with detailed error reporting
- **Performance Optimized**: Includes caching mechanisms for improved performance
- **Extensible Architecture**: Built with interfaces and dependency injection for easy customization

## Installation

Install the package via Composer:

```bash
composer require laravel-module-discovery/composer-hook
```

The package will automatically register its service provider in Laravel applications.

## Configuration

### Composer Scripts

Add the following scripts to your `composer.json` to enable automatic discovery:

```json
{
    "scripts": {
        "post-install-cmd": [
            "php artisan module:discover"
        ],
        "post-update-cmd": [
            "php artisan module:discover"
        ],
        "post-autoload-dump": [
            "php artisan module:discover"
        ]
    }
}
```

### Directory Structure

By default, the package scans the `app/Modules` directory. Create your modular structure like this:

```
app/
  Modules/
    Blog/
      Controllers/
        BlogController.php (namespace App\Modules\Blog\Controllers)
      Models/
        Post.php (namespace App\Modules\Blog\Models)
    User/
      Services/
        UserService.php (namespace App\Modules\User\Services)
```

## Usage

### Artisan Command

Run the discovery command manually:

```bash
# Basic discovery
php artisan module:discover

# With custom path
php artisan module:discover --path=custom/modules/path

# With verbose output
php artisan module:discover --verbose
```

### Programmatic Usage

```php
use LaravelModuleDiscovery\ComposerHook\Services\ClassDiscoveryService;
use LaravelModuleDiscovery\ComposerHook\Services\ComposerLoaderService;

// Discover classes in a directory
$classDiscovery = ClassDiscoveryService::make();
$discoveredClasses = $classDiscovery->discoverClasses('/path/to/modules');

// Register with Composer autoloader
$composerLoader = ComposerLoaderService::make();
$results = $composerLoader->registerMultipleNamespaces($discoveredClasses);
$composerLoader->applyRegistrations();
```

### Service Container Integration

The package registers all services in Laravel's container:

```php
// Inject via constructor
public function __construct(
    ClassDiscoveryInterface $classDiscovery,
    ComposerLoaderInterface $composerLoader
) {
    $this->classDiscovery = $classDiscovery;
    $this->composerLoader = $composerLoader;
}

// Resolve from container
$classDiscovery = app(ClassDiscoveryInterface::class);
$namespaceExtractor = app(NamespaceExtractorInterface::class);
```

## Architecture

### Interfaces (ISP Compliant)

- `ClassDiscoveryInterface`: Core class discovery operations
- `NamespaceExtractorInterface`: Namespace extraction from PHP files
- `PathResolverInterface`: Path resolution and normalization
- `ComposerLoaderInterface`: Composer autoloader integration

### Services

- `ClassDiscoveryService`: Main discovery orchestration
- `NamespaceExtractorService`: PHP token parsing and namespace extraction
- `PathResolverService`: Cross-platform path handling
- `ComposerLoaderService`: Composer ClassLoader integration

### Constants and Enums

- `DirectoryConstants`: Directory-related configuration
- `TokenConstants`: PHP token parsing constants
- `EventConstants`: Composer event identifiers
- `DiscoveryStatusEnum`: Discovery operation status tracking
- `FileTypeEnum`: Supported file type definitions

### Exception Handling

- `ModuleDiscoveryException`: General discovery errors
- `NamespaceExtractionException`: Namespace parsing errors
- `DirectoryNotFoundException`: Missing directory errors

## Examples

### Basic Discovery

```php
$discovery = ClassDiscoveryService::make();
$classes = $discovery->discoverClasses('app/Modules');

foreach ($classes as $namespace => $path) {
    echo "Found: {$namespace} => {$path}\n";
}
```

### Custom Namespace Extraction

```php
$extractor = NamespaceExtractorService::make();
$namespace = $extractor->extractNamespace('/path/to/file.php');

if ($namespace) {
    echo "Extracted namespace: {$namespace}\n";
}
```

### Path Resolution

```php
$resolver = PathResolverService::make();
$absolutePath = $resolver->resolveAbsolutePath('app/Modules');
$normalized = $resolver->normalizePath($absolutePath);
```

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run specific test class
vendor/bin/phpunit __tests__/ClassDiscoveryServiceTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage
```

### Test Structure

- `ClassDiscoveryServiceTest`: Core discovery functionality
- `NamespaceExtractorServiceTest`: Namespace extraction logic
- `ModuleDiscoverCommandTest`: Artisan command integration

## Performance Considerations

### Caching

The package includes built-in caching for:
- Namespace extraction results
- Path resolution operations
- Discovery statistics

### Optimization Tips

1. **Limit Scan Depth**: Use specific module directories rather than scanning entire application
2. **File Filtering**: Only PHP files are processed automatically
3. **Batch Operations**: Multiple namespaces are registered in batch operations
4. **Cache Management**: Clear caches periodically in development environments

## Error Handling

### Common Issues

1. **Directory Not Found**
   ```
   Directory '/path/to/modules' not found
   Suggested alternatives: app/Modules, modules, src/Modules
   ```

2. **Invalid Namespace Format**
   ```
   Invalid namespace format 'Invalid\\Namespace\\' extracted from file
   ```

3. **File Access Errors**
   ```
   Cannot read file '/path/to/file.php' for namespace extraction: Permission denied
   ```

### Debugging

Enable verbose output for detailed information:

```bash
php artisan module:discover --verbose
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

### Development Setup

```bash
git clone https://github.com/laravel-module-discovery/composer-hook.git
cd composer-hook
composer install
vendor/bin/phpunit
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- **Documentation**: [GitHub Wiki](https://github.com/laravel-module-discovery/composer-hook/wiki)
- **Issues**: [GitHub Issues](https://github.com/laravel-module-discovery/composer-hook/issues)
- **Discussions**: [GitHub Discussions](https://github.com/laravel-module-discovery/composer-hook/discussions)

## Changelog

### v1.0.0
- Initial release
- Core discovery functionality
- Composer integration
- Laravel service provider
- Comprehensive test suite
- Documentation and examples