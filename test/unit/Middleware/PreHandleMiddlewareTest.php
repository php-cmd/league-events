<?php

declare(strict_types=1);

namespace WebwareTest\CommandBus\Event\Middleware;

use League\Event\EventDispatcher;
use League\Event\EventDispatcherAware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use RuntimeException;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Event\Middleware\PreHandleMiddleware;
use Webware\CommandBus\Event\PreHandleEvent;
use Webware\CommandBus\MiddlewareInterface;

#[CoversClass(PreHandleMiddleware::class)]
final class PreHandleMiddlewareTest extends TestCase
{
    private EventDispatcherInterface&EventDispatcher $eventDispatcher;
    private PreHandleMiddleware $middleware;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->middleware      = new PreHandleMiddleware();
        $this->middleware->setEventDispatcher($this->eventDispatcher);
    }

    public function testImplementsMiddlewareInterface(): void
    {
        $this->assertInstanceOf(
            MiddlewareInterface::class,
            $this->middleware
        );
    }

    public function testImplementsEventDispatcherAware(): void
    {
        $this->assertInstanceOf(
            EventDispatcherAware::class,
            $this->middleware
        );
    }

    public function testProcessDispatchesPreHandleEvent(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult(
            $this->createMock(
                CommandInterface::class
            ),
            CommandStatus::Success,
            ['data' => 'test']
        );

        $handler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($result);

        $eventDispatched = false;
        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function (PreHandleEvent $event) use ($command, &$eventDispatched) {
                $this->assertSame($command, $event->getCommand());
                $eventDispatched = true;
            }
        );

        $actualResult = $this->middleware->process($command, $handler);

        $this->assertTrue($eventDispatched, 'PreHandleEvent was not dispatched');
        $this->assertSame($result, $actualResult);
    }

    public function testProcessCallsHandler(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult(
            $this->createMock(
                CommandInterface::class
            ),
            CommandStatus::Success,
            ['data' => 'test']
        );

        $handler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($result);

        $actualResult = $this->middleware->process($command, $handler);

        $this->assertSame($result, $actualResult);
    }

    public function testProcessReturnsHandlerResult(): void
    {
        $command        = $this->createMock(CommandInterface::class);
        $handler        = $this->createMock(CommandHandlerInterface::class);
        $expectedResult = new CommandResult(
            $this->createMock(
                CommandInterface::class
            ),
            CommandStatus::Success,
            ['key' => 'value']
        );

        $handler->method('handle')
            ->willReturn($expectedResult);

        $actualResult = $this->middleware->process($command, $handler);

        $this->assertSame($expectedResult, $actualResult);
    }

    public function testMultipleListenersReceiveEvent(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult($this->createMock(CommandInterface::class), CommandStatus::Success, []);

        $handler->method('handle')->willReturn($result);

        $listener1Called = false;
        $listener2Called = false;

        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function () use (&$listener1Called) {
                $listener1Called = true;
            }
        );

        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function () use (&$listener2Called) {
                $listener2Called = true;
            }
        );

        $this->middleware->process($command, $handler);

        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testListenerExceptionPreventsHandlerExecution(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function () {
                throw new RuntimeException('Listener failed');
            }
        );

        $handler->expects($this->never())
            ->method('handle');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Listener failed');

        $this->middleware->process($command, $handler);
    }

    public function testEventParametersCanBeModifiedByListeners(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult($this->createMock(CommandInterface::class), CommandStatus::Success, []);

        $handler->method('handle')->willReturn($result);

        $parametersCaptured = null;

        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function (PreHandleEvent $event) use (&$parametersCaptured) {
                $event->setParams(['modified' => true]);
                $parametersCaptured = $event->getParams();
            }
        );

        $this->middleware->process($command, $handler);

        $this->assertSame(['modified' => true], $parametersCaptured);
    }

    public function testMiddlewareIsReadonly(): void
    {
        $reflection = new ReflectionClass(PreHandleMiddleware::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testMiddlewareIsFinal(): void
    {
        $reflection = new ReflectionClass(PreHandleMiddleware::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function testSetEventDispatcherAllowsEventDispatching(): void
    {
        $dispatcher = new EventDispatcher();
        $middleware = new PreHandleMiddleware();
        $middleware->setEventDispatcher($dispatcher);

        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult($this->createMock(CommandInterface::class), CommandStatus::Success, []);

        $handler->method('handle')->willReturn($result);

        $eventReceived = false;
        $dispatcher->subscribeTo(
            PreHandleEvent::class,
            function () use (&$eventReceived) {
                $eventReceived = true;
            }
        );

        $middleware->process($command, $handler);

        $this->assertTrue($eventReceived);
    }
}
