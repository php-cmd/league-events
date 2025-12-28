<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event;

use Laminas\ServiceManager\Factory;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;
use Webware\Event\Container\EventDispatcherAwareDelegator;

final class ConfigProvider
{
    /**
     * @phpstan-return array{
     *      dependencies: array{
     *          delegators: array<class-string, list<class-string>>,
     *          factories: array<class-string, class-string>
     *      },
     *      Webware\CommandBus\CommandBusInterface: array{
     *         Webware\CommandBus\ConfigProvider::MIDDLEWARE_PIPELINE_KEY: array<array{
     *                                                                              middleware: class-string,
     *                                                                              priority?: int
     *                                                                        }>
     *      }
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies'             => $this->getDependencies(),
            CommandBusInterface::class => [
                BusProvider::MIDDLEWARE_PIPELINE_KEY => $this->getPipeline(),
            ],
        ];
    }

    /**
     * @return array{
     *     'delegators': array<class-string, list<class-string>>,
     *     'factories': array<class-string, class-string>
     * }
     */
    public function getDependencies(): array
    {
        return [
            'delegators' => [
                Middleware\PreHandleMiddleware::class  => [
                    EventDispatcherAwareDelegator::class,
                ],
                Middleware\PostHandleMiddleware::class => [
                    EventDispatcherAwareDelegator::class,
                ],
            ],
            'factories'  => [
                Middleware\PreHandleMiddleware::class  => Factory\InvokableFactory::class,
                Middleware\PostHandleMiddleware::class => Factory\InvokableFactory::class,
            ],
        ];
    }

    /**
     * @return array<array{middleware: class-string, priority: int}>
     */
    public function getPipeline(): array
    {
        return [
            [
                'middleware' => Middleware\PreHandleMiddleware::class,
                'priority'   => 100,
            ],
            [
                'middleware' => Middleware\PostHandleMiddleware::class,
                'priority'   => -100,
            ],
        ];
    }
}
