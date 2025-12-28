# Installation

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- Composer
- `webware/command-bus` ^0.4.0
- `league/event` ^3.0

## Installing via Composer

Install the package using Composer:

```bash
composer require webware/league-events
```

## Laminas/Mezzio Integration

If you're using Laminas or Mezzio with the component installer, the package will automatically register itself.

### Manual Configuration

If you need to manually register the configuration provider, add it to your application configuration:

```php
// config/config.php
$aggregator = new ConfigAggregator([
    \Webware\CommandBus\Event\ConfigProvider::class,
    // ... other config providers
]);
```

### Required Dependencies

The package will automatically pull in:

- **league/event** - PSR-14 event dispatcher implementation
- **webware/command-bus** - Core command bus functionality
- **webware/mezzio-eventdispatcher** - Event dispatcher integration
- **beberlei/assert** - Assertion library
- **psr/container** - PSR-11 container interface

### Suggested Dependencies

For full functionality, you should also have:

```bash
composer require laminas/laminas-servicemanager
```

## Verifying Installation

After installation, verify that the package is properly configured:

```bash
# Check if the package is installed
composer show webware/league-events

# Run a quick test to ensure middleware is registered
php -r "echo (new \Webware\CommandBus\Event\ConfigProvider())()['dependencies']['invokables'][\Webware\CommandBus\Event\Middleware\PreHandleMiddleware::class] ?? 'Not found';"
```

## Package Conflicts

This package conflicts with `webware/laminas-events`. If you have it installed, you must remove it:

```bash
composer remove webware/laminas-events
```

## Next Steps

- [Getting Started Guide](Getting-Started.md)
- [Configuration](ConfigProvider.md)
- [Usage Examples](Usage.md)
