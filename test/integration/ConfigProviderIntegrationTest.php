<?php

declare(strict_types=1);

namespace WebwareIntegrationTest\CommandBus\Event;

use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use League\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Webware\CommandBus\CommandBusInterface;
use Webware\CommandBus\ConfigProvider as BusProvider;
use Webware\CommandBus\Event\ConfigProvider;
use Webware\CommandBus\Event\Middleware\PostHandleMiddleware;
use Webware\CommandBus\Event\Middleware\PreHandleMiddleware;

final class ConfigProviderIntegrationTest extends TestCase
{
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }

    public function testConfigProviderCanBeInstantiated(): void
    {
        $provider = new ConfigProvider();
        $this->assertInstanceOf(ConfigProvider::class, $provider);
    }

    public function testInvokeReturnsValidConfiguration(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey(CommandBusInterface::class, $config);
    }

    public function testDependenciesCanBeUsedWithServiceManager(): void
    {
        $dependencies = $this->provider->getDependencies();
        /** @phpstan-ignore argument.type */
        $container = new ServiceManager($dependencies);

        $this->assertTrue($container->has(PreHandleMiddleware::class));
        $this->assertTrue($container->has(PostHandleMiddleware::class));
    }

    public function testMiddlewareHaveEventDispatcherInjected(): void
    {
        $this->markTestSkipped('Needs integration with EventDispatcher to fully test.');
        /** @phpstan-ignore deadCode.unreachable */
        $dispatcher               = new EventDispatcher();
        $dependencies             = $this->provider->getDependencies();
        $dependencies['services'] = [
            EventDispatcher::class => function ($container) use ($dispatcher) {
                return $dispatcher;
            },
        ];
        $container      = new ServiceManager($dependencies);
        $preMiddleware  = $container->get(PreHandleMiddleware::class);
        $postMiddleware = $container->get(PostHandleMiddleware::class);

        // Test that event dispatcher is working by checking if events can be dispatched
        $this->assertInstanceOf(EventDispatcher::class, $preMiddleware->getEventDispatcher());
        $this->assertInstanceOf(EventDispatcher::class, $postMiddleware->getEventDispatcher());
    }

    public function testPipelineConfigurationIsValid(): void
    {
        $pipeline = $this->provider->getPipeline();

        // Validate pre_handle configuration
        $this->assertSame(PreHandleMiddleware::class, $pipeline[0]['middleware']);
        $this->assertSame(100, $pipeline[0]['priority']);

        // Validate post_handle configuration
        $this->assertArrayHasKey('middleware', $pipeline[1]);
        $this->assertArrayHasKey('priority', $pipeline[1]);
        $this->assertSame(PostHandleMiddleware::class, $pipeline[1]['middleware']);
        $this->assertSame(-100, $pipeline[1]['priority']);
    }

    public function testBothMiddlewareCanBeRetrievedSimultaneously(): void
    {
        $dependencies             = $this->provider->getDependencies();
        $dependencies['services'] = [
            EventDispatcher::class => new EventDispatcher(),
        ];
        /** @phpstan-ignore argument.type */
        $container = new ServiceManager($dependencies);

        $preMiddleware  = $container->get(PreHandleMiddleware::class);
        $postMiddleware = $container->get(PostHandleMiddleware::class);

        $this->assertInstanceOf(PreHandleMiddleware::class, $preMiddleware);
        $this->assertInstanceOf(PostHandleMiddleware::class, $postMiddleware);
        $this->assertNotSame($preMiddleware, $postMiddleware);
    }

    public function testConfigProviderRegistersCorrectDelegators(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertArrayHasKey('delegators', $dependencies);
        $this->assertArrayHasKey(PreHandleMiddleware::class, $dependencies['delegators']);
        $this->assertArrayHasKey(PostHandleMiddleware::class, $dependencies['delegators']);

        // Verify delegators are arrays
        $this->assertIsArray($dependencies['delegators'][PreHandleMiddleware::class]);
        $this->assertIsArray($dependencies['delegators'][PostHandleMiddleware::class]);
    }

    public function testConfigProviderRegistersCorrectFactories(): void
    {
        $dependencies = $this->provider->getDependencies();

        $this->assertArrayHasKey('factories', $dependencies);
        $this->assertArrayHasKey(PreHandleMiddleware::class, $dependencies['factories']);
        $this->assertArrayHasKey(PostHandleMiddleware::class, $dependencies['factories']);

        // Verify factories point to correct classes
        $this->assertSame(
            InvokableFactory::class,
            $dependencies['factories'][PreHandleMiddleware::class]
        );
        $this->assertSame(
            InvokableFactory::class,
            $dependencies['factories'][PostHandleMiddleware::class]
        );
    }

    public function testMultipleContainerInstancesAreIndependent(): void
    {
        $dependencies             = $this->provider->getDependencies();
        $dependencies['services'] = [
            EventDispatcher::class => new EventDispatcher(),
        ];
        /** @phpstan-ignore argument.type */
        $container1 = new ServiceManager($dependencies);
        /** @phpstan-ignore argument.type */
        $container2 = new ServiceManager($dependencies);

        $middleware1 = $container1->get(PreHandleMiddleware::class);
        $middleware2 = $container2->get(PreHandleMiddleware::class);

        $this->assertInstanceOf(PreHandleMiddleware::class, $middleware1);
        $this->assertInstanceOf(PreHandleMiddleware::class, $middleware2);
    }

    public function testConfigurationStructureMatchesExpectedFormat(): void
    {
        $config = ($this->provider)();

        // Verify top-level structure
        $this->assertCount(2, $config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey(CommandBusInterface::class, $config);

        // Verify dependencies structure
        $dependencies = $config['dependencies'];
        $this->assertArrayHasKey('factories', $dependencies);
        $this->assertArrayHasKey('delegators', $dependencies);

        // Verify bus provider structure
        $busConfig = $config[CommandBusInterface::class];
        $this->assertArrayHasKey(BusProvider::MIDDLEWARE_PIPELINE_KEY, $busConfig);
    }

    public function testPipelineMiddlewareCanBeInstantiated(): void
    {
        $pipeline                 = $this->provider->getPipeline();
        $dependencies             = $this->provider->getDependencies();
        $dependencies['services'] = [
            EventDispatcher::class => new EventDispatcher(),
        ];
        /** @phpstan-ignore argument.type */
        $container = new ServiceManager($dependencies);

        foreach ($pipeline as $config) {
            $middlewareClass = $config['middleware'];
            $middleware      = $container->get($middlewareClass);

            $this->assertNotNull($middleware);
            $this->assertInstanceOf($middlewareClass, $middleware);
        }
    }

    public function testConfigProviderSupportsLaminasComponentInstaller(): void
    {
        // Verify the config provider can be called as expected by Laminas Component Installer
        $provider = new ConfigProvider();
        $config   = $provider();

        $this->assertNotEmpty($config);
    }

    public function testPriorityOrderingIsCorrect(): void
    {
        $pipeline = $this->provider->getPipeline();

        $preHandlePriority  = $pipeline[0]['priority'];
        $postHandlePriority = $pipeline[1]['priority'];

        // Pre-handle should have higher priority (larger number)
        $this->assertGreaterThan($postHandlePriority, $preHandlePriority);

        // Verify expected values
        $this->assertSame(100, $preHandlePriority);
        $this->assertSame(-100, $postHandlePriority);
    }
}
