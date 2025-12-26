<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event\Middleware;

use League\Event\EventDispatcherAware;
use Override;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\MiddlewareInterface;
use Webware\CommandBus\Event\PreHandleEvent;
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
