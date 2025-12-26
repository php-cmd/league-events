<?php

declare(strict_types=1);

namespace Webware\CommandBus\Event;

use Webware\CommandBus\ConfigProvider as BusProvider;
use Webware\Event\Container\EventDispatcherAwareDelegator;

final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies'     => $this->getDependencies(),
            BusProvider::class => [
                BusProvider::MIDDLEWARE_PIPELINE_KEY => $this->getPipeline(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
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
            'invokables' => [
                Middleware\PreHandleMiddleware::class  => Middleware\PreHandleMiddleware::class,
                Middleware\PostHandleMiddleware::class => Middleware\PostHandleMiddleware::class,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPipeline(): array
    {
        return [
            'pre_handle'  => [
                'middleware' => Middleware\PreHandleMiddleware::class,
                'priority'   => 100,
            ],
            'post_handle' => [
                'middleware' => Middleware\PostHandleMiddleware::class,
                'priority'   => -100,
            ],
        ];
    }
}
