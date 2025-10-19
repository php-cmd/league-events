<?php

declare(strict_types=1);

namespace PhpCmd\Event;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [],
        ];
    }
}
