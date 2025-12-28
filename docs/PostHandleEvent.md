# PostHandleEvent

The `PostHandleEvent` is dispatched **after** a command has been handled by its handler. This event allows listeners to inspect the command result, perform cleanup, or trigger follow-up actions.

## Class Definition

```php
<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event;

use Webware\CommandBus\CommandInterface;
use Webware\Event\MutableEvent;

final class PostHandleEvent extends MutableEvent
{
    public function __construct(
        private CommandInterface $command,
    ) {
        parent::__construct(
            target: $command
        );
    }

    public function getCommand(): CommandInterface
    {
        return $this->command;
    }
}
```

## Hierarchy

```text
Psr\EventDispatcher\StoppableEventInterface
    └── League\Event\StoppableEvent
        └── Webware\Event\MutableEvent
            └── Webware\CommandBus\Event\PostHandleEvent
```

## Properties

### `command`

- **Type:** `CommandInterface`
- **Visibility:** `private`
- **Description:** The command that was handled

## Methods

### `__construct(CommandInterface $command)`

Creates a new PostHandleEvent instance.

**Parameters:**

- `$command` - The command that was handled

**Example:**

```php
$event = new PostHandleEvent($command);
```

### `getCommand(): CommandInterface`

Returns the command associated with this event.

**Returns:** The command instance

**Example:**

```php
$command = $event->getCommand();
echo get_class($command); // App\Command\CreateUserCommand
```

## Inherited Methods

From `Webware\Event\MutableEvent`:

### `getTarget(): mixed`

Returns the target object (the command in this case).

```php
$target = $event->getTarget();
// Equivalent to $event->getCommand()
```

### `stopPropagation(): void`

Stops the event from being passed to further listeners.

```php
public function __invoke(PostHandleEvent $event): void
{
    $event->stopPropagation(); // Stop further listeners
}
```

### `isPropagationStopped(): bool`

Checks if event propagation has been stopped.

```php
if ($event->isPropagationStopped()) {
    return; // Don't process further
}
```

## Use Cases

### 1. Result Logging

Log command results:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Log\LoggerInterface;
use Webware\CommandBus\Event\PostHandleEvent;

final readonly class ResultLoggerListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        $this->logger->info('Command completed', [
            'command_class' => get_class($command),
            'timestamp' => date('c'),
        ]);
    }
}
```

### 2. Send Notifications

Send notifications after successful command execution:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Command\CreateOrderCommand;
use App\Service\NotificationService;
use Webware\CommandBus\Event\PostHandleEvent;

final readonly class OrderNotificationListener
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof CreateOrderCommand) {
            $this->notificationService->sendOrderConfirmation(
                $command->customerEmail,
                $command->orderId
            );
        }
    }
}
```

### 3. Cache Results

Cache command results for future use:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Cache\CacheItemPoolInterface;
use Webware\CommandBus\Event\PostHandleEvent;
use Webware\CommandBus\Command\CommandResultInterface;

final readonly class ResultCacheListener
{
    private const TTL = 3600; // 1 hour

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        // Only cache if command is a result
        if ($command instanceof CommandResultInterface && $command->isSuccess()) {
            $cacheKey = $this->getCacheKey($command);
            $item = $this->cache->getItem($cacheKey);

            $item->set($command->getData());
            $item->expiresAfter(self::TTL);

            $this->cache->save($item);
        }
    }

    private function getCacheKey(object $command): string
    {
        return 'result_' . md5(serialize($command));
    }
}
```

### 4. Analytics Tracking

Track command execution for analytics:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\AnalyticsService;
use Webware\CommandBus\Event\PostHandleEvent;
use Webware\CommandBus\Command\CommandResultInterface;

final readonly class AnalyticsListener
{
    public function __construct(
        private AnalyticsService $analytics,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        $this->analytics->track('command_executed', [
            'command_type' => get_class($command),
            'timestamp' => time(),
        ]);

        // Track success/failure if command is a result
        if ($command instanceof CommandResultInterface) {
            $this->analytics->track('command_result', [
                'command_type' => get_class($command),
                'success' => $command->isSuccess(),
            ]);
        }
    }
}
```

### 5. Performance Monitoring

Monitor command execution performance:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\MetricsService;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

final class PerformanceMonitorListener
{
    private array $startTimes = [];

    public function __construct(
        private MetricsService $metrics,
    ) {}

    public function onPreHandle(PreHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());
        $this->startTimes[$commandId] = hrtime(true);
    }

    public function onPostHandle(PostHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());

        if (isset($this->startTimes[$commandId])) {
            $duration = (hrtime(true) - $this->startTimes[$commandId]) / 1e6;

            $this->metrics->timing('command.duration', $duration, [
                'command' => get_class($event->getCommand()),
            ]);

            unset($this->startTimes[$commandId]);
        }
    }
}
```

### 6. Event Sourcing

Store command events for event sourcing:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Repository\EventStoreRepository;
use App\Entity\CommandEvent;
use Webware\CommandBus\Event\PostHandleEvent;

final readonly class EventSourcingListener
{
    public function __construct(
        private EventStoreRepository $eventStore,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        $commandEvent = new CommandEvent(
            commandClass: get_class($command),
            commandData: serialize($command),
            occurredAt: new \DateTimeImmutable(),
        );

        $this->eventStore->append($commandEvent);
    }
}
```

### 7. Cleanup Tasks

Perform cleanup after command execution:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Command\UploadFileCommand;
use Webware\CommandBus\Event\PostHandleEvent;

final readonly class CleanupListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof UploadFileCommand) {
            // Clean up temporary files
            if (file_exists($command->tempFilePath)) {
                unlink($command->tempFilePath);
            }
        }
    }
}
```

## Registering Listeners

### Using Configuration

```php
// config/autoload/listeners.global.php

use App\Listener\ResultLoggerListener;
use App\Listener\AnalyticsListener;
use App\Listener\OrderNotificationListener;
use Webware\CommandBus\Event\PostHandleEvent;

return [
    'listeners' => [
        PostHandleEvent::class => [
            ResultLoggerListener::class,
            AnalyticsListener::class,
            OrderNotificationListener::class,
        ],
    ],
];
```

### Combining Pre and Post Listeners

You can register listeners for both events:

```php
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

return [
    'listeners' => [
        PreHandleEvent::class => [
            AuthorizationListener::class,
            ValidationListener::class,
        ],
        PostHandleEvent::class => [
            NotificationListener::class,
            CacheListener::class,
        ],
    ],
];
```

### Single Listener for Both Events

If a listener handles both pre and post events:

```php
<?php

final class DualListener
{
    public function onPreHandle(PreHandleEvent $event): void
    {
        // Pre-handle logic
    }

    public function onPostHandle(PostHandleEvent $event): void
    {
        // Post-handle logic
    }
}

// Configuration
return [
    'listeners' => [
        PreHandleEvent::class => [
            [DualListener::class, 'onPreHandle'],
        ],
        PostHandleEvent::class => [
            [DualListener::class, 'onPostHandle'],
        ],
    ],
];
```

## Event Flow

When a command completes:

1. Command handler finishes execution
2. **PostHandleMiddleware** is triggered (priority -100)
3. PostHandleMiddleware creates a `PostHandleEvent`
4. Event is dispatched to all registered listeners
5. Listeners execute in registration/priority order
6. If any listener calls `stopPropagation()`, subsequent listeners won't run
7. Command result is returned to the caller

## Important Notes

### Command vs Result

The `PostHandleEvent` receives the **command**, not the result. However, if the command implements `CommandResultInterface`, it may contain result data:

```php
public function __invoke(PostHandleEvent $event): void
{
    $command = $event->getCommand();

    // Check if command is actually a result
    if ($command instanceof CommandResultInterface) {
        $data = $command->getData();
        $success = $command->isSuccess();
    }
}
```

### Error Handling

PostHandleEvent is **not dispatched** if the command handler throws an exception. Use exception handlers for error scenarios:

```php
// This will NOT run if handler throws exception
public function __invoke(PostHandleEvent $event): void
{
    // Only runs on successful completion
}
```

## Best Practices

### ✅ Do

- **Perform side effects**: Send emails, notifications, analytics
- **Log results**: Record what happened after command execution
- **Cache results**: Store successful results for future use
- **Clean up resources**: Release locks, delete temp files
- **Handle errors gracefully**: Catch exceptions in listeners

### ❌ Don't

- **Don't modify the command**: It's already been handled
- **Don't throw exceptions**: Unless you want to fail the entire request
- **Don't execute heavy operations**: Keep listeners fast
- **Don't assume success**: Check result status when available
- **Don't create circular dependencies**: Avoid dispatching new commands in listeners

## Differences from PreHandleEvent

| Aspect | PreHandleEvent | PostHandleEvent |

|--------|---------------|-----------------|
| **When** | Before handler execution | After handler execution |
| **Purpose** | Validation, authorization, preparation | Notifications, logging, cleanup |
| **Can stop execution** | Yes (via stopPropagation) | No (already executed) |
| **Should modify command** | Maybe (if mutable) | No |
| **Typical use cases** | Validation, auth, rate limiting | Logging, caching, notifications |
| **Error handling** | Throw to prevent execution | Catch to avoid breaking flow |

## See Also

- [PreHandleEvent](PreHandleEvent.md) - Event dispatched before command handling
- [PostHandleMiddleware](Middleware/PostHandleMiddleware.md) - Middleware that dispatches this event
- [Usage Examples](Usage.md) - More practical examples
- [Getting Started](Getting-Started.md) - Quick start guide
