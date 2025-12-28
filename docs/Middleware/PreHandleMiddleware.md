# PreHandleMiddleware

The `PreHandleMiddleware` is a command bus middleware that dispatches a `PreHandleEvent` **before** the command is passed to its handler. This allows event listeners to intercept commands for validation, authorization, logging, or other preprocessing tasks.

## Class Definition

```php
<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event\Middleware;

use League\Event\EventDispatcherAware;
use Override;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\MiddlewareInterface;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\Event\EventDispatcherAwareTrait;

final readonly class PreHandleMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareTrait;

    #[Override]
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler
    ): CommandResultInterface {
        // Let'em know
        $this->eventDispatcher()->dispatch(new PreHandleEvent($command));
        return $handler->handle($command);
    }
}
```

## Interfaces

- `MiddlewareInterface` - Command bus middleware interface
- `EventDispatcherAware` - Marker interface for event dispatcher injection

## Traits

- `EventDispatcherAwareTrait` - Provides event dispatcher functionality

## Methods

### `process(CommandInterface $command, CommandHandlerInterface $handler): CommandResultInterface`

Processes the command by dispatching a PreHandleEvent before passing it to the handler.

**Parameters:**

- `$command` - The command to process
- `$handler` - The next handler in the pipeline

**Returns:** `CommandResultInterface` - The result from the handler

**Flow:**

1. Creates a new `PreHandleEvent` with the command
2. Dispatches the event to all registered listeners
3. Calls the next handler in the pipeline
4. Returns the result

**Example:**

```php
// This is called automatically by the command bus
$result = $middleware->process($command, $handler);
```

## Configuration

### Automatic Registration

The middleware is automatically registered when you include the `ConfigProvider`:

```php
// config/config.php
$aggregator = new ConfigAggregator([
    \Webware\CommandBus\Event\ConfigProvider::class,
    // ... other providers
]);
```

### Manual Registration

You can manually register the middleware:

```php
use Webware\CommandBus\Event\Middleware\PreHandleMiddleware;
use Webware\Event\Container\EventDispatcherAwareDelegator;

return [
    'dependencies' => [
        'invokables' => [
            PreHandleMiddleware::class => PreHandleMiddleware::class,
        ],
        'delegators' => [
            PreHandleMiddleware::class => [
                EventDispatcherAwareDelegator::class,
            ],
        ],
    ],
];
```

### Pipeline Configuration

The middleware is registered in the command bus pipeline with priority 100 (runs early):

```php
use Webware\CommandBus\ConfigProvider as BusProvider;

return [
    BusProvider::class => [
        BusProvider::MIDDLEWARE_PIPELINE_KEY => [
            'pre_handle' => [
                'middleware' => PreHandleMiddleware::class,
                'priority' => 100, // High priority - runs early
            ],
        ],
    ],
];
```

## Pipeline Position

The PreHandleMiddleware runs **before** the command handler:

```text
Command Bus Pipeline:

┌─────────────────────────────────────┐
│  Other middleware (priority > 100)  │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  PreHandleMiddleware (priority 100) │ ← YOU ARE HERE
│  └─ Dispatches PreHandleEvent       │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  Other middleware (100 > p > -100)  │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  Command Handler                    │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  PostHandleMiddleware (priority -100)│
└─────────────────────────────────────┘
```

## Event Dispatcher Injection

The middleware uses the `EventDispatcherAware` interface and trait to receive the event dispatcher via a delegator factory:

```php
// The delegator injects the event dispatcher
$middleware = new PreHandleMiddleware();
$middleware->setEventDispatcher($eventDispatcher);
```

This is handled automatically by the `EventDispatcherAwareDelegator`.

## Use Cases

The PreHandleMiddleware enables various preprocessing patterns through event listeners:

### 1. Command Validation

```php
// Listener validates command before execution
final readonly class ValidationListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->validator->validate($command)) {
            throw new ValidationException();
        }
    }
}
```

### 2. Authorization

```php
// Listener checks user permissions
final readonly class AuthorizationListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        if (!$this->auth->canExecute($event->getCommand())) {
            throw new UnauthorizedException();
        }
    }
}
```

### 3. Logging

```php
// Listener logs all incoming commands
final readonly class CommandLoggerListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        $this->logger->info('Command received', [
            'command' => get_class($event->getCommand()),
        ]);
    }
}
```

### 4. Rate Limiting

```php
// Listener enforces rate limits
final readonly class RateLimitListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        if (!$this->rateLimiter->allow($event->getCommand())) {
            throw new RateLimitException();
        }
    }
}
```

## Error Handling

If a listener throws an exception during PreHandleEvent processing:

1. The exception propagates up the middleware stack
2. The command handler is **never executed**
3. Subsequent listeners are **not called**
4. PostHandleMiddleware is **not triggered**

```php
public function __invoke(PreHandleEvent $event): void
{
    // This throws and stops everything
    throw new ValidationException('Invalid command');
}

// Command handler never runs
// PostHandleEvent never fires
// Exception is returned to caller
```

## Stopping Propagation

Listeners can stop event propagation:

```php
public function __invoke(PreHandleEvent $event): void
{
    if ($this->cache->has($event->getCommand())) {
        // Stop calling other listeners
        $event->stopPropagation();
    }
}
```

However, the command will still be passed to the handler. To prevent execution, throw an exception.

## Testing

### Unit Testing the Middleware

```php
<?php

declare(strict_types=1);

namespace WebwareTest\CommandBus\Event\Middleware;

use PHPUnit\Framework\TestCase;
use Webware\CommandBus\Event\Middleware\PreHandleMiddleware;
use Webware\CommandBus\Event\PreHandleEvent;

final class PreHandleMiddlewareTest extends TestCase
{
    public function testDispatchesPreHandleEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PreHandleEvent::class));

        $handler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($this->createMock(CommandResultInterface::class));

        $middleware = new PreHandleMiddleware();
        $middleware->setEventDispatcher($eventDispatcher);

        $middleware->process($command, $handler);
    }
}
```

### Integration Testing

```php
<?php

declare(strict_types=1);

namespace WebwareIntegrationTest\CommandBus\Event\Middleware;

use PHPUnit\Framework\TestCase;

final class PreHandleMiddlewareIntegrationTest extends TestCase
{
    public function testListenerReceivesEvent(): void
    {
        $container = $this->getContainer();
        $commandBus = $container->get(CommandBusInterface::class);

        $listenerCalled = false;
        $listener = function(PreHandleEvent $event) use (&$listenerCalled) {
            $listenerCalled = true;
        };

        $this->eventDispatcher->addListener(
            PreHandleEvent::class,
            $listener
        );

        $commandBus->dispatch(new TestCommand());

        $this->assertTrue($listenerCalled);
    }
}
```

## Best Practices

### ✅ Do

- Use PreHandleMiddleware for preprocessing tasks
- Register event listeners for specific command types
- Throw exceptions to prevent command execution
- Keep listeners fast and focused
- Use priority ordering for listener execution

### ❌ Don't

- Don't execute business logic in listeners (use handlers)
- Don't rely on listener execution order unless using priorities
- Don't catch all exceptions silently
- Don't modify command state unless intended
- Don't create circular dependencies

## Performance Considerations

- PreHandleMiddleware runs for **every command**
- Each registered listener adds overhead
- Keep listeners efficient - they're on the critical path
- Use conditional logic in listeners to skip irrelevant commands
- Consider using priority to order expensive operations last

## Customization

You can create custom pre-handle middleware by following the same pattern:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use League\Event\EventDispatcherAware;
use Webware\Event\EventDispatcherAwareTrait;

final readonly class CustomPreMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareTrait;

    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler
    ): CommandResultInterface {
        // Your custom pre-processing
        $this->eventDispatcher()->dispatch(new CustomEvent($command));

        return $handler->handle($command);
    }
}
```

## See Also

- [PreHandleEvent](../PreHandleEvent.md) - The event dispatched by this middleware
- [PostHandleMiddleware](PostHandleMiddleware.md) - Middleware for post-processing
- [Usage Examples](../Usage.md) - Practical examples
- [Getting Started](../Getting-Started.md) - Quick start guide
- [ConfigProvider](../ConfigProvider.md) - Configuration details
