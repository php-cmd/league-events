<?php

declare(strict_types=1);

namespace PhpCmd\Event\Middleware;

use League\Event\EventDispatcherAware;
use Override;
use PhpCmd\CmdBus\Command\CommandResultInterface;
use PhpCmd\CmdBus\CommandHandlerInterface;
use PhpCmd\CmdBus\CommandInterface;
use PhpCmd\CmdBus\MiddlewareInterface;
use PhpCmd\Event\PostHandleEvent;
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
