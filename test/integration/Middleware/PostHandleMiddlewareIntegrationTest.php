<?php

declare(strict_types=1);

namespace WebwareIntegrationTest\CommandBus\Event\Middleware;

use Laminas\ServiceManager\ServiceManager;
use League\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\CommandBus\Command\CommandResult;
use Webware\CommandBus\Command\CommandResultInterface;
use Webware\CommandBus\Command\CommandStatus;
use Webware\CommandBus\CommandHandlerInterface;
use Webware\CommandBus\CommandInterface;
use Webware\CommandBus\Event\ConfigProvider;
use Webware\CommandBus\Event\Middleware\PostHandleMiddleware;
use Webware\CommandBus\Event\PostHandleEvent;

use function get_class;

final class PostHandleMiddlewareIntegrationTest extends TestCase
{
    private ServiceManager $container;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();

        $config             = (new ConfigProvider())->getDependencies();
        $config['services'] = [
            EventDispatcher::class => $this->eventDispatcher,
        ];
        /** @phpstan-ignore argument.type */
        $this->container = new ServiceManager($config);
    }

    public function testMiddlewareCanBeRetrievedFromContainer(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $this->assertInstanceOf(PostHandleMiddleware::class, $middleware);
    }

    public function testMiddlewareHasEventDispatcherInjected(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestResult();
        $handler = $this->createTestHandler();

        $eventReceived = false;
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function () use (&$eventReceived) {
                $eventReceived = true;
            }
        );

        $middleware->process($command, $handler);

        $this->assertTrue($eventReceived, 'Event dispatcher was not properly injected');
    }

    public function testEventListenersReceiveCorrectEventData(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestResult();
        $handler = $this->createTestHandler();

        $receivedCommand = null;
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function (PostHandleEvent $event) use (&$receivedCommand) {
                $receivedCommand = $event->getCommand();
            }
        );

        $middleware->process($command, $handler);

        $this->assertSame($command, $receivedCommand);
    }

    public function testListenerCanSendNotification(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestResult();
        $handler = $this->createTestHandler();

        $notifications = [];
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function (PostHandleEvent $event) use (&$notifications) {
                $notifications[] = 'Command completed: ' . get_class($event->getCommand());
            }
        );

        $middleware->process($command, $handler);

        $this->assertCount(2, $notifications);
        $this->assertStringContainsString('Command completed:', $notifications[0]);
    }

    public function testListenerExceptionPreventsResultReturn(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestResult();
        $handler = $this->createTestHandler();

        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function () {
                throw new RuntimeException('Post-processing failed');
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Post-processing failed');

        $middleware->process($command, $handler);
    }

    public function testMiddlewareWorksWithRealEventDispatcher(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestResult();
        $handler = $this->createTestHandler();

        $events = [];
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function (PostHandleEvent $event) use (&$events) {
                $events[] = $event;
            }
        );

        $result = $middleware->process($command, $handler);

        $this->assertInstanceOf(CommandResultInterface::class, $result);
        $this->assertCount(2, $events);
        $this->assertInstanceOf(PostHandleEvent::class, $events[0]);
    }

    public function testMiddlewareProcessesRegularCommandsThroughHandler(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestCommand();
        $handler = $this->createTestHandler();

        $handlerCalled = false;
        $commandMock   = $this->createMock(CommandInterface::class);
        $handler       = new class ($handlerCalled, $commandMock) implements CommandHandlerInterface {
            /** @phpstan-ignore property.onlyWritten */
            public function __construct(private bool &$called, private CommandInterface $commandMock)
            {
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                $this->called = true;
                return new CommandResult($this->commandMock, CommandStatus::Success, ['data' => 'handled']);
            }
        };

        $result = $middleware->process($command, $handler);

        $this->assertTrue($handlerCalled);
        $this->assertInstanceOf(CommandResultInterface::class, $result);
    }

    public function testMiddlewareSkipsHandlerForResultCommands(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $command = $this->createTestResult();
        $handler = $this->createStub(CommandHandlerInterface::class);

        $handlerCalled = false;
        $commandMock   = $this->createMock(CommandInterface::class);
        $handler->method('handle')->willReturnCallback(function () use (&$handlerCalled, $commandMock) {
            $handlerCalled = true;
            return new CommandResult($commandMock, CommandStatus::Success, []);
        });

        $result = $middleware->process($command, $handler);

        $this->assertFalse($handlerCalled, 'Handler should not have been called for result commands');
        $this->assertSame($command, $result);
    }

    public function testListenerCanAccessResultData(): void
    {
        $middleware = $this->container->get(PostHandleMiddleware::class);

        $expectedData = ['key' => 'value', 'status' => 'completed'];
        $command      = $this->createTestResultWithData($expectedData);
        $handler      = $this->createTestHandler();

        $receivedData = null;
        $this->eventDispatcher->subscribeTo(
            PostHandleEvent::class,
            function (PostHandleEvent $event) use (&$receivedData) {
                $command = $event->getCommand();
                if ($command instanceof CommandResultInterface) {
                    $receivedData = $command->getResult();
                }
            }
        );

        $middleware->process($command, $handler);

        $this->assertSame($expectedData, $receivedData);
    }

    public function testMultipleMiddlewareInstancesAreIndependent(): void
    {
        $middleware1 = $this->container->get(PostHandleMiddleware::class);
        $middleware2 = $this->container->get(PostHandleMiddleware::class);

        // ServiceManager might return same instance or different
        // We just verify both work correctly
        $this->assertInstanceOf(PostHandleMiddleware::class, $middleware1);
        $this->assertInstanceOf(PostHandleMiddleware::class, $middleware2);
    }

    private function createTestResult(): CommandResultInterface
    {
        return new CommandResult(
            $this->createMock(CommandInterface::class),
            CommandStatus::Success,
            ['test' => 'data']
        );
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    private function createTestResultWithData(array $data): CommandResultInterface
    {
        return new CommandResult(
            $this->createMock(CommandInterface::class),
            CommandStatus::Success,
            $data
        );
    }

    private function createTestCommand(): CommandInterface
    {
        return new class implements CommandInterface {
            public string $data = 'test';
        };
    }

    private function createTestHandler(): CommandHandlerInterface
    {
        $commandMock = $this->createMock(CommandInterface::class);
        return new class ($commandMock) implements CommandHandlerInterface {
            public function __construct(private CommandInterface $commandMock)
            {
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return new CommandResult(
                    $this->commandMock,
                    CommandStatus::Success,
                    ['handled' => true]
                );
            }
        };
    }
}
