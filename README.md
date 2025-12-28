# league-events

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://php.net)

League Event Dispatcher support for the webware/command-bus package. This library provides PSR-14 event dispatcher middleware that dispatches events before and after command handling, enabling event-driven architecture patterns in your PHP applications.

## Features

- ✅ **PSR-14 Event Dispatcher** - Standards-compliant event dispatching
- 🔄 **Pre & Post Handle Events** - Hook into command execution before and after handling
- ⚡ **Middleware Pipeline** - Integrates seamlessly with command bus middleware
- 🎯 **Type Safe** - PHP 8.2+ with strict types and readonly properties
- 🧪 **Fully Tested** - Comprehensive unit and integration tests
- 📦 **Laminas/Mezzio Ready** - Auto-configuration support

## Quick Example

```php
use Webware\CommandBus\Event\PreHandleEvent;
use Psr\Log\LoggerInterface;

// Create a listener that logs all commands
final readonly class CommandLoggerListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $this->logger->info('Command received', [
            'command' => get_class($event->getCommand()),
        ]);
    }
}

// Register the listener
return [
    'listeners' => [
        PreHandleEvent::class => [
            CommandLoggerListener::class,
        ],
    ],
];

// Now all commands will be logged automatically!
$commandBus->dispatch(new CreateUserCommand('user@example.com'));
```

## Installation

```bash
composer require webware/league-events
```

For detailed installation instructions, see the [Installation Guide](docs/Installation.md).

## Documentation

### Getting Started

- 📘 [Installation Guide](docs/Installation.md) - Installation and setup
- 🚀 [Getting Started](docs/Getting-Started.md) - Your first event listener
- 💡 [Usage Examples](docs/Usage.md) - Real-world examples and patterns

### API Reference

- 📋 [PreHandleEvent](docs/PreHandleEvent.md) - Event dispatched before command handling
- 📋 [PostHandleEvent](docs/PostHandleEvent.md) - Event dispatched after command handling
- ⚙️ [PreHandleMiddleware](docs/Middleware/PreHandleMiddleware.md) - Pre-handle middleware API
- ⚙️ [PostHandleMiddleware](docs/Middleware/PostHandleMiddleware.md) - Post-handle middleware API
- 🔧 [ConfigProvider](docs/ConfigProvider.md) - Configuration and setup

## Requirements

- PHP 8.2, 8.3, 8.4, or 8.5
- [webware/command-bus](https://github.com/tyrsson/command-bus) ^0.4.0
- [league/event](https://event.thephpleague.com) ^3.0
- [webware/mezzio-eventdispatcher](https://github.com/tyrsson/mezzio-eventdispatcher) ^0.1.0

## How It Works

The package provides two middleware components that integrate with the command bus:

```text
┌─────────────────────────────────────┐
│         Command Dispatched          │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│  PreHandleMiddleware (Priority 100) │
│  └─ Dispatches PreHandleEvent       │
│     • Validation                    │
│     • Authorization                 │
│     • Logging                       │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│         Command Handler             │
│         Executes Business Logic     │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│  PostHandleMiddleware (Priority -100)│
│  └─ Dispatches PostHandleEvent      │
│     • Notifications                 │
│     • Caching                       │
│     • Analytics                     │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│         Result Returned             │
└─────────────────────────────────────┘
```

## Common Use Cases

### Validation

```php
public function __invoke(PreHandleEvent $event): void
{
    if (!$this->validator->validate($event->getCommand())) {
        throw new ValidationException();
    }
}
```

### Authorization

```php
public function __invoke(PreHandleEvent $event): void
{
    if (!$this->auth->canExecute($event->getCommand())) {
        throw new UnauthorizedException();
    }
}
```

### Notifications

```php
public function __invoke(PostHandleEvent $event): void
{
    if ($event->getCommand() instanceof CreateOrderCommand) {
        $this->emailService->sendOrderConfirmation();
    }
}
```

### Logging & Analytics

```php
public function __invoke(PostHandleEvent $event): void
{
    $this->analytics->track('command_executed', [
        'command' => get_class($event->getCommand()),
    ]);
}
```

See the [Usage Examples](docs/Usage.md) for more patterns and real-world scenarios.

## Development

### Running Tests

```bash
# Run all checks (code style, static analysis, tests)
composer check

# Run just the tests
composer test
composer test-integration

# Run static analysis
composer sa

# Fix code style issues
composer cs-fix
```

### Code Quality

This project maintains high code quality standards:

- ✅ **Laminas Coding Standard** - PSR-12 compliant
- ✅ **PHPStan Level 10** - Maximum static analysis
- ✅ **100% Type Coverage** - All parameters and returns typed
- ✅ **PHP 8.2+ Features** - Strict types, readonly, enums

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests and quality checks pass (`composer check`)
5. Submit a pull request

## License

This project is licensed under the BSD-3-Clause License. See the [LICENSE](LICENSE) file for details.

## Support

- 📝 [Documentation](docs/)
- 🐛 [Issue Tracker](https://github.com/tyrsson/league-events/issues)
- 💬 [Discussions](https://github.com/tyrsson/league-events/discussions)

## Related Projects

- [webware/command-bus](https://github.com/tyrsson/command-bus) - Command bus implementation
- [webware/mezzio-eventdispatcher](https://github.com/tyrsson/mezzio-eventdispatcher) - Event dispatcher for Mezzio
- [league/event](https://event.thephpleague.com) - PSR-14 event dispatcher

---

Made with ❤️ by the Webware team
