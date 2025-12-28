<?php

declare(strict_types=1);

namespace WebwareTest\CommandBus\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\Event\ConfigProvider;
use Webware\CommandBus\Event\Middleware\PostHandleMiddleware;
use Webware\CommandBus\Event\Middleware\PreHandleMiddleware;
use Webware\Event\Container\EventDispatcherAwareDelegator;

#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends TestCase
{
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }

    public function testInvokeReturnsDependenciesKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('dependencies', $config);
    }

    public function testInvokeReturnsBusProviderKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey(CommandBusInterface::class, $config);
    }

    public function testInvokeReturnsCompleteStructure(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey(CommandBusInterface::class, $config);
        $this->assertCount(2, $config);
    }

    public function testGetDependenciesReturnsFactories(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertArrayHasKey('factories', $dependencies);
    }

    public function testGetDependenciesReturnsDelegators(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertArrayHasKey('delegators', $dependencies);
    }

    public function testGetDependenciesRegistersPreHandleMiddlewareDelegator(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertArrayHasKey(PreHandleMiddleware::class, $dependencies['delegators']);
        $this->assertIsArray($dependencies['delegators'][PreHandleMiddleware::class]);
        $this->assertContains(
            EventDispatcherAwareDelegator::class,
            $dependencies['delegators'][PreHandleMiddleware::class]
        );
    }

    public function testGetDependenciesRegistersPostHandleMiddlewareDelegator(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertArrayHasKey(PostHandleMiddleware::class, $dependencies['delegators']);
        $this->assertContains(
            EventDispatcherAwareDelegator::class,
            $dependencies['delegators'][PostHandleMiddleware::class]
        );
    }

    public function testGetDependenciesHasExpectedStructure(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertCount(2, $dependencies);
        $this->assertArrayHasKey('factories', $dependencies);
        $this->assertArrayHasKey('delegators', $dependencies);
    }

    public function testGetPipelineRegistersPreHandleMiddleware(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertContains(PreHandleMiddleware::class, $pipeline[0]);
    }

    public function testGetPipelineRegistersPostHandleMiddleware(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertContains(PostHandleMiddleware::class, $pipeline[1]);
    }

    public function testGetPipelinePreHandleHasCorrectStructure(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertArrayHasKey('middleware', $pipeline[0]);
        $this->assertArrayHasKey('priority', $pipeline[0]);
    }

    public function testGetPipelinePostHandleHasCorrectStructure(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertArrayHasKey('middleware', $pipeline[1]);
        $this->assertArrayHasKey('priority', $pipeline[1]);
    }

    public function testGetPipelinePreHandleHasCorrectMiddleware(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertSame(
            PreHandleMiddleware::class,
            $pipeline[0]['middleware']
        );
    }

    public function testGetPipelinePostHandleHasCorrectMiddleware(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertSame(
            PostHandleMiddleware::class,
            $pipeline[1]['middleware']
        );
    }

    public function testGetPipelinePreHandleHasHighPriority(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertSame(100, $pipeline[0]['priority']);
    }

    public function testGetPipelinePostHandleHasLowPriority(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertSame(-100, $pipeline[1]['priority']);
    }

    public function testGetPipelinePreHandlePriorityIsHigherThanPostHandle(): void
    {
        $pipeline = $this->provider->getPipeline();

        $this->assertGreaterThan(
            $pipeline[1]['priority'],
            $pipeline[0]['priority']
        );
    }

    public function testProviderIsFinal(): void
    {
        $reflection = new ReflectionClass(ConfigProvider::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function testGetDependenciesIsPublic(): void
    {
        $reflection = new ReflectionClass(ConfigProvider::class);
        $method     = $reflection->getMethod('getDependencies');
        $this->assertTrue($method->isPublic());
    }

    public function testGetPipelineIsPublic(): void
    {
        $reflection = new ReflectionClass(ConfigProvider::class);
        $method     = $reflection->getMethod('getPipeline');
        $this->assertTrue($method->isPublic());
    }

    public function testInvokeIsPublic(): void
    {
        $reflection = new ReflectionClass(ConfigProvider::class);
        $method     = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic());
    }

    public function testGetDependenciesReturnsCorrectTypes(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertIsArray($dependencies['factories']);
        $this->assertIsArray($dependencies['delegators']);

        foreach ($dependencies['factories'] as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsString($value);
        }

        foreach ($dependencies['delegators'] as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsArray($value);
        }
    }

    public function testGetPipelineReturnsCorrectTypes(): void
    {
        $pipeline = $this->provider->getPipeline();

        foreach ($pipeline as $key => $config) {
            $this->assertIsArray($config);
            $this->assertArrayHasKey('middleware', $config);
            $this->assertArrayHasKey('priority', $config);
            $this->assertIsString($config['middleware']);
            $this->assertIsInt($config['priority']);
        }
    }

    public function testInvokeReturnsSameConfigurationOnMultipleCalls(): void
    {
        $config1 = ($this->provider)();
        $config2 = ($this->provider)();

        $this->assertEquals($config1, $config2);
    }
}
