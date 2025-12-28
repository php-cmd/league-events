# PostHandleMiddleware

The `PostHandleMiddleware` is a command bus middleware that dispatches a `PostHandleEvent` **after** the command has been handled. This allows event listeners to perform post-processing tasks like logging, notifications, caching, or cleanup.

## Class Definition

```php
<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event\Middleware;

use League\Event\EventDispatcherAware;
use Override;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\MiddlewareInterface;
use Webware\CommandBus\Event\PostHandleEvent;
use Webware\Event\EventDispatcherAwareTrait;

final readonly class PostHandleMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareTrait;

    #[Override]
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler
    ): CommandResultInterface {
        // Custom processing logic for this middleware
        if ($command instanceof CommandResultInterface) {
            $this->eventDispatcher()->dispatch(new PostHandleEvent($command));
            // Return the CommandResult
            return $command;
        }
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

Processes the command by first calling the handler, then dispatching a PostHandleEvent.

**Parameters:**

- `$command` - The command to process (may be a result)
- `$handler` - The next handler in the pipeline

**Returns:** `CommandResultInterface` - The result from the handler

**Flow:**

1. Checks if command is already a `CommandResultInterface`
2. If yes, dispatches `PostHandleEvent` and returns the result
3. If no, calls the handler to execute the command
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
use Webware\CommandBus\Event\Middleware\PostHandleMiddleware;
use Webware\Event\Container\EventDispatcherAwareDelegator;

return [
    'dependencies' => [
        'invokables' => [
            PostHandleMiddleware::class => PostHandleMiddleware::class,
        ],
        'delegators' => [
            PostHandleMiddleware::class => [
                EventDispatcherAwareDelegator::class,
            ],
        ],
    ],
];
```

### Pipeline Configuration

The middleware is registered in the command bus pipeline with priority -100 (runs late):

```php
use Webware\CommandBus\ConfigProvider as BusProvider;

return [
    BusProvider::class => [
        BusProvider::MIDDLEWARE_PIPELINE_KEY => [
            'post_handle' => [
                'middleware' => PostHandleMiddleware::class,
                'priority' => -100, // Low priority - runs late
            ],
        ],
    ],
];
```

## Pipeline Position

The PostHandleMiddleware runs **after** the command handler:

```text
Command Bus Pipeline:

┌─────────────────────────────────────┐
│  PreHandleMiddleware (priority 100) │
│  └─ Dispatches PreHandleEvent       │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  Command Handler                    │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  Other middleware (-100 < p < 100)  │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  PostHandleMiddleware (priority -100)│ ← YOU ARE HERE
│  └─ Dispatches PostHandleEvent      │
└─────────────┬───────────────────────┘
              │
              ▼
┌─────────────────────────────────────┐
│  Other middleware (priority < -100) │
└─────────────────────────────────────┘
```

## Event Dispatcher Injection

The middleware uses the `EventDispatcherAware` interface and trait to receive the event dispatcher via a delegator factory:

```php
// The delegator injects the event dispatcher
$middleware = new PostHandleMiddleware();
$middleware->setEventDispatcher($eventDispatcher);
```

This is handled automatically by the `EventDispatcherAwareDelegator`.

## Use Cases

The PostHandleMiddleware enables various post-processing patterns through event listeners:

### 1. Result Logging

```php
// Listener logs command results
final readonly class ResultLoggerListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        $this->logger->info('Command completed', [
            'command' => get_class($command),
            'success' => $command instanceof CommandResultInterface
                ? $command->isSuccess()
                : true,
        ]);
    }
}
```

### 2. Notifications

```php
// Listener sends notifications
final readonly class NotificationListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof CreateOrderCommand) {
            $this->notificationService->sendOrderConfirmation();
        }
    }
}
```

### 3. Result Caching

```php
// Listener caches successful results
final readonly class CacheListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof CommandResultInterface && $command->isSuccess()) {
            $this->cache->set(
                $this->getCacheKey($command),
                $command->getData()
            );
        }
    }
}
```

### 4. Analytics

```php
// Listener tracks command analytics
final readonly class AnalyticsListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $this->analytics->track('command_completed', [
            'command_type' => get_class($event->getCommand()),
            'timestamp' => time(),
        ]);
    }
}
```

## Error Handling

### Handler Exceptions

If the command handler throws an exception:

1. PostHandleMiddleware is **not triggered**
2. PostHandleEvent is **not dispatched**
3. Exception propagates up the middleware stack

```php
// Handler throws exception
public function handle(CommandInterface $command): CommandResultInterface
{
    throw new \RuntimeException('Handler failed');
}

// PostHandleMiddleware never runs
// PostHandleEvent never fires
// Exception goes directly to caller
```

### Listener Exceptions

If a listener throws an exception during PostHandleEvent processing:

1. The exception propagates up the middleware stack
2. Subsequent listeners are **not called**
3. The command result is **not returned** to the caller

```php
public function __invoke(PostHandleEvent $event): void
{
    // This throws and stops everything
    throw new \RuntimeException('Listener failed');
}

// Remaining listeners don't run
// Result is not returned
// Exception goes to caller
```

**Best Practice:** Catch exceptions in post-handle listeners:

```php
public function __invoke(PostHandleEvent $event): void
{
    try {
        $this->emailService->send($event);
    } catch (\Exception $e) {
        // Log but don't throw - don't break the pipeline
        $this->logger->error('Email failed', ['error' => $e->getMessage()]);
    }
}
```

## Command Result Check

The middleware checks if the command is already a `CommandResultInterface`:

```php
if ($command instanceof CommandResultInterface) {
    // Command is already a result, dispatch event
    $this->eventDispatcher()->dispatch(new PostHandleEvent($command));
    return $command;
}

// Otherwise, call handler
return $handler->handle($command);
```

This pattern accommodates command bus implementations where:

- Commands may be transformed into results by earlier middleware
- Results may pass through the pipeline as commands

## Stopping Propagation

Listeners can stop event propagation:

```php
public function __invoke(PostHandleEvent $event): void
{
    // Stop calling other listeners
    $event->stopPropagation();
}
```

This prevents subsequent listeners from running but still returns the result to the caller.

## Testing

### Unit Testing the Middleware

```php
<?php

declare(strict_types=1);

namespace WebwareTest\CommandBus\Event\Middleware;

use PHPUnit\Framework\TestCase;
use Webware\CommandBus\Event\Middleware\PostHandleMiddleware;
use Webware\CommandBus\Event\PostHandleEvent;

final class PostHandleMiddlewareTest extends TestCase
{
    public function testDispatchesPostHandleEventForResult(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PostHandleEvent::class));

        // Handler should not be called for results
        $handler->expects($this->never())
            ->method('handle');

        $middleware = new PostHandleMiddleware();
        $middleware->setEventDispatcher($eventDispatcher);

        $result = $middleware->process($command, $handler);

        $this->assertSame($command, $result);
    }

    public function testCallsHandlerForNonResult(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result = $this->createMock(CommandResultInterface::class);

        $handler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($result);

        $middleware = new PostHandleMiddleware();
        $middleware->setEventDispatcher($eventDispatcher);

        $actualResult = $middleware->process($command, $handler);

        $this->assertSame($result, $actualResult);
    }
}
```

### Integration Testing

```php
<?php

declare(strict_types=1);

namespace WebwareIntegrationTest\CommandBus\Event\Middleware;

use PHPUnit\Framework\TestCase;

final class PostHandleMiddlewareIntegrationTest extends TestCase
{
    public function testListenerReceivesEvent(): void
    {
        $container = $this->getContainer();
        $commandBus = $container->get(CommandBusInterface::class);

        $listenerCalled = false;
        $listener = function(PostHandleEvent $event) use (&$listenerCalled) {
            $listenerCalled = true;
        };

        $this->eventDispatcher->addListener(
            PostHandleEvent::class,
            $listener
        );

        $commandBus->dispatch(new TestCommand());

        $this->assertTrue($listenerCalled);
    }
}
```

## Best Practices

### ✅ Do

- Use PostHandleMiddleware for side effects (notifications, logging, etc.)
- Catch exceptions in listeners to avoid breaking the pipeline
- Check if command is a `CommandResultInterface` before accessing result data
- Keep listeners fast - they're on the critical path
- Use for cleanup tasks (delete temp files, release locks)

### ❌ Don't

- Don't throw exceptions unless you want to fail the entire request
- Don't modify the command or result (they're already processed)
- Don't execute heavy synchronous operations
- Don't assume the handler succeeded (check result status)
- Don't create circular dependencies (dispatching commands in listeners)

## Performance Considerations

- PostHandleMiddleware runs for **every command**
- Each registered listener adds overhead
- Failed listeners can break the entire pipeline
- Consider async processing for expensive operations
- Use conditional logic to skip irrelevant commands

## Customization

You can create custom post-handle middleware by following the same pattern:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use League\Event\EventDispatcherAware;
use Webware\Event\EventDispatcherAwareTrait;

final readonly class CustomPostMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareTrait;

    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler
    ): CommandResultInterface {
        $result = $handler->handle($command);

        // Your custom post-processing
        $this->eventDispatcher()->dispatch(new CustomEvent($result));

        return $result;
    }
}
```

## Comparison with PreHandleMiddleware

| Aspect | PreHandleMiddleware | PostHandleMiddleware |

|--------|--------------------|--------------------|
| **Execution** | Before handler | After handler |
| **Priority** | 100 (high - early) | -100 (low - late) |
| **Purpose** | Validation, authorization | Notifications, logging |
| **Can prevent execution** | Yes (throw exception) | No (already executed) |
| **Typical use** | Preprocessing | Post-processing |
| **Error impact** | Prevents handler execution | Breaks result return |

## See Also

- [PostHandleEvent](../PostHandleEvent.md) - The event dispatched by this middleware
- [PreHandleMiddleware](PreHandleMiddleware.md) - Middleware for pre-processing
- [Usage Examples](../Usage.md) - Practical examples
- [Getting Started](../Getting-Started.md) - Quick start guide
- [ConfigProvider](../ConfigProvider.md) - Configuration details
