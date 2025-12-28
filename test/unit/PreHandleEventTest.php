<?php

declare(strict_types=1);

namespace WebwareTest\CommandBus\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\Event\MutableEvent;
use Webware\Event\MutableEventInterface;

#[CoversClass(PreHandleEvent::class)]
final class PreHandleEventTest extends TestCase
{
    public function testConstructorSetsCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $this->assertSame($command, $event->getCommand());
    }

    public function testGetCommandReturnsCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $result = $event->getCommand();

        $this->assertSame($command, $result);
        $this->assertInstanceOf(CommandInterface::class, $result);
    }

    public function testGetTargetReturnsCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $target = $event->getTarget();

        $this->assertSame($command, $target);
    }

    public function testEventIsMutable(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $this->assertInstanceOf(MutableEvent::class, $event);
    }

    public function testCommandPropertyIsReadonly(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $firstCall  = $event->getCommand();
        $secondCall = $event->getCommand();

        // Same instance returned each time
        $this->assertSame($firstCall, $secondCall);
    }

    public function testMultipleEventsAreIndependent(): void
    {
        $command1 = $this->createMock(CommandInterface::class);
        $command2 = $this->createMock(CommandInterface::class);

        $event1 = new PreHandleEvent($command1);
        $event2 = new PreHandleEvent($command2);

        $this->assertNotSame($event1, $event2);
        $this->assertNotSame($event1->getCommand(), $event2->getCommand());
    }

    public function testEventHasName(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $this->assertSame(PreHandleEvent::class, $event->getName());
    }

    public function testEventCanSetAndGetParams(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $params = ['key' => 'value', 'foo' => 'bar'];
        $event->setParams($params);

        $this->assertSame($params, $event->getParams());
    }

    public function testEventParamsDefaultToEmptyArray(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $this->assertEmpty($event->getParams());
    }

    public function testEventTargetCanBeRetrieved(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $this->assertNotNull($event->getTarget());
        $this->assertInstanceOf(CommandInterface::class, $event->getTarget());
    }

    public function testEventImplementsMutableEventInterface(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PreHandleEvent($command);

        $this->assertInstanceOf(MutableEventInterface::class, $event);
    }
}
