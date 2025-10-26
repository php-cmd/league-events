<?php

declare(strict_types=1);

namespace PhpCmd\Event\Middleware;

use League\Event\EventDispatcherAware;
use League\Event\EventDispatcherAwareBehavior;
use Override;
use PhpCmd\CmdBus\CommandHandlerInterface;
use PhpCmd\CmdBus\CommandInterface;
use PhpCmd\CmdBus\MiddlewareInterface;
use PhpCmd\Event\PreHandleEvent;

final class PreHandleMiddleware implements MiddlewareInterface, EventDispatcherAware
{
    use EventDispatcherAwareBehavior;

    #[Override]
    public function process(CommandInterface $command, CommandHandlerInterface $handler): mixed
    {
        // Let'em know
        $this->eventDispatcher()->dispatch(new PreHandleEvent($command));
        return $handler->handle($command);
    }
}
