<?php

declare(strict_types=1);

namespace WebwareIntegrationTest\CommandBus\Event\Middleware;

use InvalidArgumentException;
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
use Webware\CommandBus\Event\Middleware\PreHandleMiddleware;
use Webware\CommandBus\Event\PreHandleEvent;

final class PreHandleMiddlewareIntegrationTest extends TestCase
{
    private ServiceManager $container;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $config                = (new ConfigProvider())->getDependencies();
        $config['services']    = [
            EventDispatcher::class => $this->eventDispatcher,
        ];
        /** @phpstan-ignore argument.type */
        $this->container = new ServiceManager($config);
    }

    public function testMiddlewareCanBeRetrievedFromContainer(): void
    {
        $middleware = $this->container->get(PreHandleMiddleware::class);

        $this->assertInstanceOf(PreHandleMiddleware::class, $middleware);
    }

    public function testMiddlewareHasEventDispatcherInjected(): void
    {
        $middleware = $this->container->get(PreHandleMiddleware::class);

        $command = $this->createTestCommand();
        $handler = $this->createTestHandler();

        $eventReceived = false;
        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function () use (&$eventReceived) {
                $eventReceived = true;
            }
        );

        $middleware->process($command, $handler);

        $this->assertTrue($eventReceived, 'Event dispatcher was not properly injected');
    }

    public function testEventListenersReceiveCorrectEventData(): void
    {
        $middleware = $this->container->get(PreHandleMiddleware::class);

        $command = $this->createTestCommand();
        $handler = $this->createTestHandler();

        $receivedCommand = null;
        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function (PreHandleEvent $event) use (&$receivedCommand) {
                $receivedCommand = $event->getCommand();
            }
        );

        $middleware->process($command, $handler);

        $this->assertSame($command, $receivedCommand);
    }

    public function testListenerCanValidateCommand(): void
    {
        $middleware = $this->container->get(PreHandleMiddleware::class);

        $command = new TestCommand(''); // Empty data to trigger validation
        $handler = $this->createTestHandler();

        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function (PreHandleEvent $event) {
                $command = $event->getCommand();
                if ($command instanceof TestCommand && empty($command->data)) {
                    throw new InvalidArgumentException('Command data cannot be empty');
                }
            }
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Command data cannot be empty');

        $middleware->process($command, $handler);
    }

    public function testListenerExceptionPreventsHandlerExecution(): void
    {
        $middleware = $this->container->get(PreHandleMiddleware::class);

        $command = $this->createTestCommand();
        $handler = $this->createStub(CommandHandlerInterface::class);

        $handlerCalled = false;
        $handler->method('handle')->willReturnCallback(function () use (&$handlerCalled) {
            $handlerCalled = true;
            return new CommandResult($this->createMock(CommandInterface::class), CommandStatus::Success, []);
        });

        $this->eventDispatcher->subscribeTo(
            PreHandleEvent::class,
            function () {
                throw new RuntimeException('Validation failed');
            }
        );

        try {
            $middleware->process($command, $handler);
        } catch (RuntimeException $e) {
            // Expected exception
        }

        $this->assertFalse($handlerCalled, 'Handler should not have been called');
    }

    private function createTestCommand(): CommandInterface
    {
        return new TestCommand();
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
                return new CommandResult($this->commandMock, CommandStatus::Success, ['handled' => true]);
            }
        };
    }
}
