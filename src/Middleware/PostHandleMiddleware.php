<?php

declare(strict_types=1);

namespace PhpCmd\Event\Middleware;

use League\Event\EventDispatcherAware;
use League\Event\EventDispatcherAwareBehavior;
use Override;
use PhpCmd\CmdBus\Command\CommandResult;
use PhpCmd\CmdBus\CommandHandlerInterface;
use PhpCmd\CmdBus\CommandInterface;
use PhpCmd\CmdBus\MiddlewareInterface;
use PhpCmd\Event\PostHandleEvent;

final class PostHandleMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareBehavior;

    #[Override]
    public function process(
        CommandInterface $command,
        CommandHandlerInterface $handler
    ): mixed {
        // Custom processing logic for this middleware
        if ($command instanceof CommandResult) {
            $this->eventDispatcher()->dispatch(new PostHandleEvent($command));
            // Return the CommandResult
            return $command;
        }
        return $handler->handle($command);
    }
}
