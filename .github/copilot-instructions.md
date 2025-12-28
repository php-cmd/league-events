# GitHub Copilot Instructions for webware/league-events

## Project Overview

This is a PHP library that provides League Event Dispatcher support for the webware/command-bus package. It implements PSR-14 event dispatcher middleware for command bus operations, enabling event-driven architecture patterns in Mezzio and Laminas-based applications.

**Key Purpose**: Dispatches events before and after command handling in a command bus middleware pipeline.

## Architecture & Design Patterns

### Core Components

1. **Middleware Pattern**: Pre and Post handle middleware for command bus pipeline
2. **PSR-14 Events**: Implements PSR-14 event dispatcher standard via League Event
3. **Dependency Injection**: Laminas ServiceManager integration via ConfigProvider
4. **Event Dispatcher Aware**: Uses delegator pattern to inject event dispatchers into middleware

### Key Classes

- `PreHandleEvent`: Event dispatched before command handling
- `PostHandleEvent`: Event dispatched after command handling
- `PreHandleMiddleware`: Middleware that dispatches pre-handle events
- `PostHandleMiddleware`: Middleware that dispatches post-handle events
- `ConfigProvider`: Laminas/Mezzio configuration provider

## Code Standards & Quality

### PHP Standards

- **PHP Version**: Minimum PHP 8.2, supports 8.2, 8.3, 8.4, 8.5
- **Strict Types**: ALL files must use `declare(strict_types=1);`
- **Type Hints**: Use strict type hints for all parameters and return types
- **Readonly**: Use `readonly` properties and classes where appropriate
- **Override Attribute**: Use `#[Override]` attribute when implementing interface methods or overriding parent methods

### Coding Standards

- **Standard**: Laminas Coding Standard (via `laminas/laminas-coding-standard`)
- **Static Analysis**: PHPStan level 10 (maximum strictness)
- **Run Checks**: `composer cs-check` for code style, `composer cs-fix` to auto-fix
- **Static Analysis**: `composer sa` for PHPStan analysis

### Code Style Requirements

1. **Final Classes**: Use `final` for classes that shouldn't be extended
2. **Readonly Classes**: Use `readonly` for immutable classes
3. **Namespacing**: Follow PSR-4 autoloading - `Webware\CommandBus\Event\`
4. **Visibility**: Always declare visibility (public/private/protected)
5. **Documentation**: Add PHPDoc blocks for complex methods and arrays
6. **Array Types**: Use typed arrays in PHPDoc (e.g., `@return array<string, mixed>`)
7. **Constructor Property Promotion**: Use for concise property declaration
8. **Single Responsibility Principle**: Keep classes focused on a single task

### Testing

- **Framework**: PHPUnit 11.5+
- **Test Structure**:
  - Unit tests: `test/unit/` → `WebwareTest\CommandBus\Event\` namespace
  - Integration tests: `test/integration/` → `WebwareIntegrationTest\CommandBus\Event\` namespace
- **Run Tests**:
  - `composer test` for unit tests
  - `composer test-integration` for integration tests
  - `composer check` runs all checks (CS, SA, tests)

## Dependencies & Integration

### Required Dependencies

- `webware/command-bus`: ^0.4.0 - Core command bus implementation
- `league/event`: ^3.0 - PSR-14 event dispatcher implementation
- `webware/mezzio-eventdispatcher`: ^0.1.0 - Mezzio event dispatcher integration
- `beberlei/assert`: ^3.3 - Assertion library
- `psr/container`: ^2.0 - PSR-11 container interface

### Conflicts

- Do NOT use `webware/laminas-events` - this package conflicts with it

### Suggested

- `laminas/laminas-servicemanager`: For DI container support

## Development Workflow

### When Creating New Features

1. Add middleware classes in `src/Middleware/`
2. Add event classes in `src/`
3. Register in `ConfigProvider::getDependencies()` and `ConfigProvider::getPipeline()`
4. Use delegators for event dispatcher injection (see existing pattern)
5. Write unit tests in `test/unit/` with corresponding namespace
6. Run `composer check` before committing

### When Writing Code

- Start with interface/contract definition
- Use constructor property promotion for conciseness
- Keep classes small and focused (Single Responsibility Principle)
- Favor composition over inheritance
- Use readonly properties for immutability
- Always use strict type declarations

### Middleware Pipeline

Middleware priority system:
- Pre-handle: Priority 100 (runs early)
- Post-handle: Priority -100 (runs late)
- Higher numbers = earlier execution

## Common Patterns

### Event Class Pattern

```php
final class MyEvent extends MutableEvent
{
    public function __construct(
        private readonly CommandInterface $command,
    ) {
        parent::__construct(target: $command);
    }

    public function getCommand(): CommandInterface
    {
        return $this->command;
    }
}
```

### Middleware Pattern

```php
final readonly class MyMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareTrait;

    #[Override]
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler
    ): CommandResultInterface {
        $this->eventDispatcher()->dispatch(new MyEvent($command));
        return $handler->handle($command);
    }
}
```

### ConfigProvider Pattern

Register middleware in both dependencies and pipeline:

```php
'invokables' => [
    Middleware\MyMiddleware::class => Middleware\MyMiddleware::class,
],
'delegators' => [
    Middleware\MyMiddleware::class => [
        EventDispatcherAwareDelegator::class,
    ],
],
```

## File Structure Conventions

- Source code: `src/`
- Tests: `test/unit/` and `test/integration/`
- Configuration: Root level XML/NEON files
- Stubs: `stubs/` for PHPStan type stubs
- Documentation: `docs/` (currently minimal)

## Important Notes

- This library is part of the Webware ecosystem (command-bus integration)
- Designed for Mezzio/Laminas applications but PSR-standard compatible
- Uses League Event (not Laminas Event Manager)
- All middleware must implement `EventDispatcherAware` interface
- Events extend `MutableEvent` from `webware/mezzio-eventdispatcher`

## Quality Gates

Before any commit, ensure:
1. ✅ `composer cs-check` passes
2. ✅ `composer sa` passes (PHPStan level 10)
3. ✅ `composer test` passes
4. ✅ `composer test-integration` passes

Or simply run: `composer check`
