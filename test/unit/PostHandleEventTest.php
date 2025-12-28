<?php

declare(strict_types=1);

namespace WebwareTest\CommandBus\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Event\PostHandleEvent;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\Event\MutableEvent;
use Webware\Event\MutableEventInterface;

#[CoversClass(PostHandleEvent::class)]
final class PostHandleEventTest extends TestCase
{
    public function testConstructorSetsCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $this->assertSame($command, $event->getCommand());
    }

    public function testGetCommandReturnsCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $result = $event->getCommand();

        $this->assertSame($command, $result);
        $this->assertInstanceOf(CommandInterface::class, $result);
    }

    public function testGetTargetReturnsCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $target = $event->getTarget();

        $this->assertSame($command, $target);
    }

    public function testEventIsMutable(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $this->assertInstanceOf(MutableEvent::class, $event);
    }

    public function testCommandPropertyCanBeModified(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $firstCall  = $event->getCommand();
        $secondCall = $event->getCommand();

        // Same instance returned each time
        $this->assertSame($firstCall, $secondCall);
    }

    public function testMultipleEventsAreIndependent(): void
    {
        $command1 = $this->createMock(CommandInterface::class);
        $command2 = $this->createMock(CommandInterface::class);

        $event1 = new PostHandleEvent($command1);
        $event2 = new PostHandleEvent($command2);

        $this->assertNotSame($event1, $event2);
        $this->assertNotSame($event1->getCommand(), $event2->getCommand());
    }

    public function testPostHandleEventIsSeparateFromPreHandleEvent(): void
    {
        $command   = $this->createMock(CommandInterface::class);
        $postEvent = new PostHandleEvent($command);
        $preEvent  = new PreHandleEvent($command);

        $this->assertNotSame($postEvent, $preEvent);
        $this->assertNotEquals($postEvent, $preEvent);
    }

    public function testEventHasName(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $this->assertSame(PostHandleEvent::class, $event->getName());
    }

    public function testEventCanSetAndGetParams(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $params = ['result' => 'success', 'data' => ['id' => 123]];
        $event->setParams($params);

        $this->assertSame($params, $event->getParams());
    }

    public function testEventParamsDefaultToEmptyArray(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $this->assertEmpty($event->getParams());
    }

    public function testEventTargetCanBeRetrieved(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $this->assertNotNull($event->getTarget());
        $this->assertInstanceOf(CommandInterface::class, $event->getTarget());
    }

    public function testEventImplementsMutableEventInterface(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $event   = new PostHandleEvent($command);

        $this->assertInstanceOf(MutableEventInterface::class, $event);
    }
}
