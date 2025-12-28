<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event\Middleware;

use League\Event\EventDispatcherAware;
use Override;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Event\PostHandleEvent;
use Webware\CommandBus\MiddlewareInterface;
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
