# Usage Examples

This guide provides practical examples of using the League Events package with the command bus.

## Table of Contents

- [Basic Event Listening](#basic-event-listening)
- [Advanced Patterns](#advanced-patterns)
- [Real-World Scenarios](#real-world-scenarios)
- [Best Practices](#best-practices)

## Basic Event Listening

### Example 1: Simple Logging

Log every command that enters the system:

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

        $this->logger->info('Command dispatched', [
            'command_type' => get_class($command),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
```

**Configuration:**

```php
// config/autoload/listeners.global.php
return [
    'listeners' => [
        \Webware\CommandBus\Event\PreHandleEvent::class => [
            \App\Listener\CommandLoggerListener::class,
        ],
    ],
];
```

### Example 2: Result Notification

Send notifications after successful command execution:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Service\NotificationService;
use Webware\CommandBus\Event\PostHandleEvent;

final readonly class NotificationListener
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        // Send notification based on command type
        $this->notificationService->notify(
            'Command completed: ' . get_class($command)
        );
    }
}
```

## Advanced Patterns

### Example 3: Command Caching

Cache command results for idempotent commands:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Cache\CacheItemPoolInterface;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

final class CachingListener
{
    private const TTL = 3600; // 1 hour

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {}

    public function onPreHandle(PreHandleEvent $event): void
    {
        $command = $event->getCommand();
        $cacheKey = $this->getCacheKey($command);

        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            // Stop propagation and return cached result
            $event->stopPropagation();
            $event->setResult($item->get());
        }
    }

    public function onPostHandle(PostHandleEvent $event): void
    {
        $command = $event->getCommand();
        $cacheKey = $this->getCacheKey($command);

        $item = $this->cache->getItem($cacheKey);
        $item->set($event->getResult());
        $item->expiresAfter(self::TTL);

        $this->cache->save($item);
    }

    private function getCacheKey(object $command): string
    {
        return 'command_' . md5(serialize($command));
    }
}
```

### Example 4: Command Authorization

Verify permissions before command execution:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use App\Security\AuthorizationService;
use Webware\CommandBus\Event\PreHandleEvent;
use App\Exception\UnauthorizedException;

final readonly class AuthorizationListener
{
    public function __construct(
        private AuthorizationService $authService,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if (!$this->authService->canExecute($command)) {
            throw new UnauthorizedException(
                sprintf(
                    'User is not authorized to execute %s',
                    get_class($command)
                )
            );
        }
    }
}
```

### Example 5: Performance Profiling

Measure and report command execution times:

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Psr\Log\LoggerInterface;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

final class ProfilingListener
{
    private array $startTimes = [];

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function onPreHandle(PreHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());
        $this->startTimes[$commandId] = hrtime(true);
    }

    public function onPostHandle(PostHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());
        $duration = (hrtime(true) - $this->startTimes[$commandId]) / 1e6; // Convert to ms

        $this->logger->debug('Command execution time', [
            'command' => get_class($event->getCommand()),
            'duration_ms' => round($duration, 2),
        ]);

        unset($this->startTimes[$commandId]);

        // Alert on slow commands
        if ($duration > 1000) {
            $this->logger->warning('Slow command detected', [
                'command' => get_class($event->getCommand()),
                'duration_ms' => round($duration, 2),
            ]);
        }
    }
}
```

## Real-World Scenarios

### Scenario 1: E-Commerce Order Processing

Complete order processing with events:

```php
<?php

declare(strict_types=1);

namespace App\Listener\Order;

use App\Command\CreateOrderCommand;
use App\Service\EmailService;
use App\Service\InventoryService;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

// Check inventory before order creation
final readonly class InventoryCheckListener
{
    public function __construct(
        private InventoryService $inventory,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof CreateOrderCommand) {
            foreach ($command->items as $item) {
                if (!$this->inventory->isAvailable($item->productId, $item->quantity)) {
                    throw new \RuntimeException(
                        "Product {$item->productId} is out of stock"
                    );
                }
            }
        }
    }
}

// Send confirmation email after order creation
final readonly class OrderConfirmationListener
{
    public function __construct(
        private EmailService $emailService,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof CreateOrderCommand) {
            $this->emailService->sendOrderConfirmation(
                $command->customerEmail,
                $event->getResult()->getData()
            );
        }
    }
}
```

### Scenario 2: User Registration Workflow

Multi-step user registration:

```php
<?php

declare(strict_types=1);

namespace App\Listener\User;

use App\Command\RegisterUserCommand;
use App\Service\EmailService;
use App\Service\AnalyticsService;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

// Validate email uniqueness
final readonly class EmailUniqueValidator
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function __invoke(PreHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof RegisterUserCommand) {
            if ($this->userRepository->emailExists($command->email)) {
                throw new \RuntimeException('Email already registered');
            }
        }
    }
}

// Send welcome email and track analytics
final readonly class UserRegistrationListener
{
    public function __construct(
        private EmailService $emailService,
        private AnalyticsService $analytics,
    ) {}

    public function __invoke(PostHandleEvent $event): void
    {
        $command = $event->getCommand();

        if ($command instanceof RegisterUserCommand) {
            $userData = $event->getResult()->getData();

            // Send welcome email
            $this->emailService->sendWelcomeEmail($command->email);

            // Track registration
            $this->analytics->track('user_registered', [
                'user_id' => $userData['userId'],
                'source' => $command->source ?? 'direct',
            ]);
        }
    }
}
```

### Scenario 3: Audit Trail

Comprehensive audit logging:

```php
<?php

declare(strict_types=1);

namespace App\Listener\Audit;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\Event\PostHandleEvent;

final class AuditTrailListener
{
    private array $commandData = [];

    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private ?string $currentUserId = null,
    ) {}

    public function onPreHandle(PreHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());

        $this->commandData[$commandId] = [
            'command_type' => get_class($event->getCommand()),
            'command_data' => serialize($event->getCommand()),
            'user_id' => $this->currentUserId,
            'timestamp' => new \DateTimeImmutable(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
    }

    public function onPostHandle(PostHandleEvent $event): void
    {
        $commandId = spl_object_id($event->getCommand());
        $data = $this->commandData[$commandId] ?? [];

        $auditLog = new AuditLog(
            commandType: $data['command_type'],
            commandData: $data['command_data'],
            userId: $data['user_id'],
            timestamp: $data['timestamp'],
            ipAddress: $data['ip_address'],
            success: $event->getResult()->isSuccess(),
            resultData: serialize($event->getResult()),
        );

        $this->auditLogRepository->save($auditLog);

        unset($this->commandData[$commandId]);
    }
}
```

## Best Practices

### 1. Keep Listeners Focused

Each listener should have a single responsibility:

```php
// ✅ Good - Single responsibility
final readonly class EmailNotificationListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $this->emailService->sendNotification($event);
    }
}

// ❌ Bad - Multiple responsibilities
final readonly class MultiPurposeListener
{
    public function __invoke(PostHandleEvent $event): void
    {
        $this->emailService->sendNotification($event);
        $this->logger->log($event);
        $this->cache->clear($event);
        $this->analytics->track($event);
    }
}
```

### 2. Use Type Checking

Check command types before processing:

```php
// ✅ Good
public function __invoke(PreHandleEvent $event): void
{
    $command = $event->getCommand();

    if (!$command instanceof CreateUserCommand) {
        return; // Not interested in this command
    }

    // Process CreateUserCommand
}

// ❌ Bad
public function __invoke(PreHandleEvent $event): void
{
    // Assumes all commands have certain properties
    $this->process($event->getCommand()->email);
}
```

### 3. Handle Errors Gracefully

```php
// ✅ Good
public function __invoke(PostHandleEvent $event): void
{
    try {
        $this->emailService->send($event);
    } catch (\Exception $e) {
        $this->logger->error('Failed to send email', [
            'error' => $e->getMessage(),
        ]);
        // Don't throw - don't break the pipeline
    }
}
```

### 4. Use Dependency Injection

```php
// ✅ Good
final readonly class MyListener
{
    public function __construct(
        private LoggerInterface $logger,
        private EmailService $emailService,
    ) {}
}

// ❌ Bad
final class MyListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        $logger = new Logger(); // Hard dependency
    }
}
```

### 5. Document Event Behavior

```php
/**
 * Validates user permissions before command execution.
 *
 * This listener checks if the current user has permission to execute
 * the given command. If not authorized, it throws an UnauthorizedException
 * which will prevent the command from being executed.
 *
 * @throws UnauthorizedException When user lacks required permissions
 */
final readonly class AuthorizationListener
{
    public function __invoke(PreHandleEvent $event): void
    {
        // Implementation
    }
}
```

## Next Steps

- Review [PreHandleEvent API](PreHandleEvent.md)
- Review [PostHandleEvent API](PostHandleEvent.md)
- Learn about [middleware configuration](Middleware/PreHandleMiddleware.md)
- Understand the [ConfigProvider](ConfigProvider.md)
