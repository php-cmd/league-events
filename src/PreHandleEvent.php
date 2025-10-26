<?php

declare(strict_types=1);

namespace PhpCmd\Event;

use PhpCmd\CmdBus\CommandInterface;
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
