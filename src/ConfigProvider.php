<?php

declare(strict_types=1);

namespace PhpCmd\Event;

use PhpCmd\CmdBus\ConfigProvider as BusProvider;
use Webware\Event\Container\EventDispatcherAwareDelegator;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            BusProvider::class => [
                BusProvider::MIDDLEWARE_PIPELINE_KEY  => $this->getPipeline(),
            ]
        ];
    }

    public function getDependencies(): array
    {
        return [
            'delegators' => [
                Middleware\PreHandleMiddleware::class => [
                    EventDispatcherAwareDelegator::class,
                ],
                Middleware\PostHandleMiddleware::class => [
                    EventDispatcherAwareDelegator::class,
                ],
            ],
            'invokables'  => [
                Middleware\PreHandleMiddleware::class  => Middleware\PreHandleMiddleware::class,
                Middleware\PostHandleMiddleware::class => Middleware\PostHandleMiddleware::class,
            ],
        ];
    }

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
