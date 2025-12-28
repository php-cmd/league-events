<?php

declare(strict_types=1);

namespace WebwareIntegrationTest\CommandBus\Event\Middleware;

use Webware\CommandBus\CommandInterface;

/**
 * Test command class for integration tests
 */
final class TestCommand implements CommandInterface
{
    public function __construct(
        public readonly string $data = 'test'
    ) {
    }
}
