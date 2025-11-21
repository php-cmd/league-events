<?php

declare(strict_types=1);

namespace PhpCmd\Event\Middleware;

use League\Event\EventDispatcherAware;
use Override;
use PhpCmd\CmdBus\CommandHandlerInterface;
use PhpCmd\CmdBus\CommandInterface;
use PhpCmd\CmdBus\Command\CommandResultInterface;
use PhpCmd\CmdBus\MiddlewareInterface;
use PhpCmd\Event\PreHandleEvent;
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
