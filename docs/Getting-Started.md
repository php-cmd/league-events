# Getting Started

## Overview

The `webware/league-events` package provides PSR-14 event dispatcher support for the command bus pattern. It allows you to dispatch events before and after command handling, enabling event-driven architecture in your application.

## Quick Start

### 1. Installation

```bash
composer require webware/league-events
```

### 2. Basic Setup

The package automatically registers itself with Laminas/Mezzio applications. No additional configuration is needed for basic usage.

### 3. Understanding the Flow

When a command is processed through the command bus, events are dispatched at two key points:

```text
┌─────────────────────────────────────────────────┐
│         Command enters the bus                  │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│  PreHandleMiddleware (Priority: 100)            │
│  ├─ Dispatches PreHandleEvent                   │
│  └─ Listeners can inspect/modify command        │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│         Command Handler executes                │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│  PostHandleMiddleware (Priority: -100)          │
│  ├─ Dispatches PostHandleEvent                  │
│  └─ Listeners can inspect result                │
└────────────────┬────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────┐
│         Command result returned                 │
└─────────────────────────────────────────────────┘
```

## Your First Event Listener

### Step 1: Create a Command

```php
<?php

declare(strict_types=1);

namespace App\Command;

use Webware\CommandBus\CommandInterface;

final readonly class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $name,
    ) {}
}
```

### Step 2: Create a Command Handler

```php
<?php

declare(strict_types=1);

namespace App\Handler;

use App\Command\CreateUserCommand;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandResult;

final class CreateUserHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): CommandResultInterface
    {
        assert($command instanceof CreateUserCommand);

        // Your business logic here
        $userId = $this->userRepository->create($command->email, $command->name);

        return CommandResult::success(['userId' => $userId]);
    }
}
```

### Step 3: Create an Event Listener

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Command\CreateUserCommand;
use Psr\Log\LoggerInterface;
use Webware\CommandBus\Event\PreHandleEvent;

final readonly class LogCommandListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof CreateUserCommand) {
            $this->logger->info('Creating user', [
                'email' => $command->email,
                'name' => $command->name,
            ]);
        }
    }
}
```

### Step 4: Register Your Listener

Add your listener to your application's event dispatcher configuration:

```php
// config/autoload/listeners.global.php

use App\Listener\LogCommandListener;
use Webware\CommandBus\Event\PreHandleEvent;

return [
    'listeners' => [
        PreHandleEvent::class => [
            LogCommandListener::class,
        ],
    ],
];
```

### Step 5: Dispatch Your Command

```php
<?php

use App\Command\CreateUserCommand;
use Webware\CommandBus\CommandBusInterface;

// In your controller or service
$command = new CreateUserCommand(
    email: 'user@example.com',
    name: 'John Doe'
);

$result = $commandBus->dispatch($command);

if ($result->isSuccess()) {
    echo "User created with ID: " . $result->getData()['userId'];
}
```

## What Just Happened?

1. The command was dispatched to the command bus
2. **PreHandleMiddleware** intercepted it and dispatched a `PreHandleEvent`
3. Your `LogCommandListener` received the event and logged the command details
4. The command handler executed and created the user
5. **PostHandleMiddleware** would dispatch a `PostHandleEvent` (if you had listeners for it)
6. The result was returned

## Common Use Cases

### Logging and Auditing

Use `PreHandleEvent` to log all commands entering the system:

```php
final readonly class AuditLogListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        $this->auditLog->record([
            'command' => get_class($event->getCommand()),
            'timestamp' => time(),
            'user' => $this->currentUser->getId(),
        ]);
    }
}
```

### Performance Monitoring

Use both events to measure command execution time:

```php
final class PerformanceMonitorListener
{
    private array $timings = [];

    public function onPreHandle(PreHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());
        $this->timings[$commandId] = microtime(true);
    }

    public function onPostHandle(PostHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());
        $duration = microtime(true) - $this->timings[$commandId];

        $this->metrics->record('command.duration', $duration, [
            'command' => get_class($event->getCommand()),
        ]);
    }
}
```

### Validation

Use `PreHandleEvent` to perform additional validation:

```php
final readonly class ValidationListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->validator->isValid($command)) {
            throw new ValidationException($this->validator->getErrors());
        }
    }
}
```

## Next Steps

- Learn about [event classes](PreHandleEvent.md) in detail
- Explore [middleware configuration](Middleware/PreHandleMiddleware.md)
- See more [usage examples](Usage.md)
- Understand the [configuration system](ConfigProvider.md)
