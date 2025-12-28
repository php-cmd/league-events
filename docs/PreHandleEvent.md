# PreHandleEvent

The `PreHandleEvent` is dispatched **before** a command is handled by its handler. This event allows listeners to inspect, validate, or prepare the command before execution.

## Class Definition

```php
<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event;

use Webware\CommandBus\CommandInterface;
use Webware\Event\MutableEvent;

final class PreHandleEvent extends MutableEvent
{
    public function __construct(
        private readonly CommandInterface $command,
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
            └── Webware\CommandBus\Event\PreHandleEvent
```

## Properties

### `command` (readonly)

- **Type:** `CommandInterface`
- **Visibility:** `private`
- **Description:** The command that is about to be handled

## Methods

### `__construct(CommandInterface $command)`

Creates a new PreHandleEvent instance.

**Parameters:**

- `$command` - The command that will be handled

**Example:**

```php
$event = new PreHandleEvent($command);
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
public function __invoke(PreHandleEvent $event): void
{
    if ($this->cache->has($event->getCommand())) {
        $event->stopPropagation(); // Stop processing
    }
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

### 1. Command Validation

Validate commands before they reach the handler:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Validator\CommandValidator;
use Webware\CommandBus\Event\PreHandleEvent;
use App\Exception\ValidationException;

final readonly class CommandValidationListener
{
    public function __construct(
        private CommandValidator $validator,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        $errors = $this->validator->validate($command);

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
```

### 2. Command Logging

Log all incoming commands:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Log\LoggerInterface;
use Webware\CommandBus\Event\PreHandleEvent;

final readonly class CommandLoggerListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        $this->logger->info('Command received', [
            'command_class' => get_class($command),
            'command_data' => $this->serializeCommand($command),
            'timestamp' => date('c'),
        ]);
    }

    private function serializeCommand(object $command): array
    {
        // Serialize command for logging
        return get_object_vars($command);
    }
}
```

### 3. Authorization Check

Verify user permissions:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Security\Authorization;
use Webware\CommandBus\Event\PreHandleEvent;
use App\Exception\UnauthorizedException;

final readonly class AuthorizationListener
{
    public function __construct(
        private Authorization $authorization,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->authorization->canExecute($command)) {
            throw new UnauthorizedException(
                'User is not authorized to execute ' . get_class($command)
            );
        }
    }
}
```

### 4. Command Caching

Check cache before executing command:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Cache\CacheItemPoolInterface;
use Webware\CommandBus\Event\PreHandleEvent;

final readonly class CacheCheckListener
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();
        $cacheKey = $this->getCacheKey($command);

        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            // Stop event propagation - don't execute the command
            $event->stopPropagation();

            // You might need to set result somewhere accessible
            // This depends on your implementation
        }
    }

    private function getCacheKey(object $command): string
    {
        return 'cmd_' . md5(serialize($command));
    }
}
```

### 5. Rate Limiting

Implement rate limiting for commands:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\RateLimiter;
use Webware\CommandBus\Event\PreHandleEvent;
use App\Exception\RateLimitExceededException;

final readonly class RateLimitListener
{
    public function __construct(
        private RateLimiter $rateLimiter,
        private ?string $userId = null,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();
        $key = $this->userId . ':' . get_class($command);

        if (! $this->rateLimiter->allow($key, limit: 10, window: 60)) {
            throw new RateLimitExceededException(
                'Rate limit exceeded for ' . get_class($command)
            );
        }
    }
}
```

### 6. Command Enrichment

Add contextual data to commands:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Command\EnrichableCommandInterface;
use Webware\CommandBus\Event\PreHandleEvent;

final readonly class CommandEnrichmentListener
{
    public function __construct(
        private ?string $userId = null,
        private ?string $ipAddress = null,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof EnrichableCommandInterface) {
            $command->setUserId($this->userId);
            $command->setIpAddress($this->ipAddress);
            $command->setTimestamp(new \DateTimeImmutable());
        }
    }
}
```

## Registering Listeners

### Using Configuration

```php
// config/autoload/listeners.global.php

use App\Listener\CommandValidationListener;
use App\Listener\AuthorizationListener;
use App\Listener\CommandLoggerListener;
use Webware\CommandBus\Event\PreHandleEvent;

return [
    'listeners' => [
        PreHandleEvent::class => [
            CommandValidationListener::class,  // Runs first
            AuthorizationListener::class,      // Then this
            CommandLoggerListener::class,      // Finally this
        ],
    ],
];
```

### Using Priority

Some event dispatchers support priority ordering:

```php
return [
    'listeners' => [
        PreHandleEvent::class => [
            [
                'listener' => AuthorizationListener::class,
                'priority' => 100, // High priority - runs early
            ],
            [
                'listener' => CommandLoggerListener::class,
                'priority' => -100, // Low priority - runs late
            ],
        ],
    ],
];
```

## Event Flow

When a command is dispatched:

1. Command enters the command bus pipeline
2. **PreHandleMiddleware** is triggered (priority 100)
3. PreHandleMiddleware creates a `PreHandleEvent`
4. Event is dispatched to all registered listeners
5. Listeners execute in registration/priority order
6. If any listener calls `stopPropagation()`, subsequent listeners won't run
7. If no exception is thrown, command proceeds to handler
8. Handler executes the command

## Best Practices

### ✅ Do

- **Validate early**: Perform validation in PreHandleEvent listeners
- **Fail fast**: Throw exceptions immediately when validation fails
- **Keep listeners focused**: One responsibility per listener
- **Use type checking**: Check command type before processing
- **Log appropriately**: Use proper log levels (info, warning, error)

### ❌ Don't

- **Don't modify command state** unless it implements a mutable interface
- **Don't execute business logic**: That belongs in the handler
- **Don't catch all exceptions**: Let validation errors propagate
- **Don't assume command type**: Always check with `instanceof`
- **Don't create side effects**: PreHandle should be about preparation, not execution

## See Also

- [PostHandleEvent](PostHandleEvent.md) - Event dispatched after command handling
- [PreHandleMiddleware](Middleware/PreHandleMiddleware.md) - Middleware that dispatches this event
- [Usage Examples](Usage.md) - More practical examples
- [Getting Started](Getting-Started.md) - Quick start guide
