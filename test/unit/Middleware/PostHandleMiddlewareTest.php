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
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Event\Middleware\PostHandleMiddleware;
use Webware\CommandBus\Event\PostHandleEvent;
use Webware\CommandBus\MiddlewareInterface;

#[CoversClass(PostHandleMiddleware::class)]
final class PostHandleMiddlewareTest extends TestCase
{
    private EventDispatcherInterface&EventDispatcher $eventDispatcher;
    private PostHandleMiddleware $middleware;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->middleware      = new PostHandleMiddleware();
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

    public function testProcessDispatchesPostHandleEventForResult(): void
    {
        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        // Handler should not be called when command is already a result
        $handler->expects($this->never())
            ->method('handle');

        $eventDispatched = false;
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function (PostHandleEvent $event) use ($command, &$eventDispatched) {
                $this->assertSame($command, $event->getCommand());
                $eventDispatched = true;
            }
        );

        $result = $this->middleware->process($command, $handler);

        $this->assertTrue($eventDispatched, 'PostHandleEvent was not dispatched');
        $this->assertSame($command, $result);
    }

    public function testProcessCallsHandlerForNonResultCommand(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult(
            $this->createMock(CommandInterface::class),
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

    public function testProcessReturnsResultDirectlyWhenCommandIsResult(): void
    {
        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        // Handler should not be called
        $handler->expects($this->never())
            ->method('handle');

        $result = $this->middleware->process($command, $handler);

        $this->assertSame($command, $result);
    }

    public function testMultipleListenersReceiveEvent(): void
    {
        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $listener1Called = false;
        $listener2Called = false;

        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function () use (&$listener1Called) {
                $listener1Called = true;
            }
        );

        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function () use (&$listener2Called) {
                $listener2Called = true;
            }
        );

        $this->middleware->process($command, $handler);

        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }

    public function testListenerExceptionPreventsResultReturn(): void
    {
        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function () {
                throw new RuntimeException('Listener failed');
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Listener failed');

        $this->middleware->process($command, $handler);
    }

    public function testEventParametersCanBeModifiedByListeners(): void
    {
        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $parametersCaptured = null;

        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function (PostHandleEvent $event) use (&$parametersCaptured) {
                $event->setParams(['modified' => true]);
                $parametersCaptured = $event->getParams();
            }
        );

        $this->middleware->process($command, $handler);

        $this->assertSame(['modified' => true], $parametersCaptured);
    }

    public function testMiddlewareIsReadonly(): void
    {
        $reflection = new ReflectionClass(PostHandleMiddleware::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testMiddlewareIsFinal(): void
    {
        $reflection = new ReflectionClass(PostHandleMiddleware::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function testSetEventDispatcherAllowsEventDispatching(): void
    {
        $dispatcher = new EventDispatcher();
        $middleware = new PostHandleMiddleware();
        $middleware->setEventDispatcher($dispatcher);

        $command = $this->createMock(CommandResultInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);

        $eventReceived = false;
        $dispatcher->subscribeTo(
            PostHandleEvent::class,
            function () use (&$eventReceived) {
                $eventReceived = true;
            }
        );

        $middleware->process($command, $handler);

        $this->assertTrue($eventReceived);
    }

    public function testProcessHandlesRegularCommandCorrectly(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $handler = $this->createMock(CommandHandlerInterface::class);
        $result  = new CommandResult(
            $this->createMock(
                CommandInterface::class
            ),
            CommandStatus::Success,
            ['test' => 'data']
        );

        $handler->expects($this->once())
            ->method('handle')
            ->with($command)
            ->willReturn($result);

        $actualResult = $this->middleware->process($command, $handler);

        $this->assertSame($result, $actualResult);
        $this->assertInstanceOf(CommandResultInterface::class, $actualResult);
    }

    public function testProcessWithMixedCommandAndResultInterfaces(): void
    {
        // Use a mock instead of anonymous class to avoid interface implementation issues
        $commandResult = $this->createMock(CommandResultInterface::class);

        $handler = $this->createMock(CommandHandlerInterface::class);

        $eventDispatched = false;
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function () use (&$eventDispatched) {
                $eventDispatched = true;
            }
        );

        // Handler should not be called since it's a CommandResultInterface
        $handler->expects($this->never())
            ->method('handle');

        $result = $this->middleware->process($commandResult, $handler);

        $this->assertTrue($eventDispatched);
        $this->assertSame($commandResult, $result);
    }
}
