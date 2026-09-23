<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Container;

class ContainerLazyLoadingTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_lazy_binding_not_cached(): void
    {
        $callCount = 0;
        $this->container->bindLazy('lazy.service', function () use (&$callCount) {
            $callCount++;
            return ['call' => $callCount];
        });

        $a = $this->container->get('lazy.service');
        $b = $this->container->get('lazy.service');

        $this->assertSame(1, $a['call']);
        $this->assertSame(2, $b['call']);
    }

    public function test_singleton_is_cached(): void
    {
        $callCount = 0;
        $this->container->singleton('singleton.service', function () use (&$callCount) {
            $callCount++;
            return ['call' => $callCount];
        });

        $a = $this->container->get('singleton.service');
        $b = $this->container->get('singleton.service');

        $this->assertSame(1, $callCount); // factory called only once
        $this->assertSame($a, $b); // same instance
    }

    public function test_forget_clears_singleton(): void
    {
        $callCount = 0;
        $this->container->singleton('reset.service', function () use (&$callCount) {
            $callCount++;
            return ['call' => $callCount];
        });

        $a = $this->container->get('reset.service');
        $this->container->forget('reset.service');
        $b = $this->container->get('reset.service');

        $this->assertSame(2, $callCount); // factory called twice
        $this->assertNotSame($a, $b);
    }

    public function test_flush_clears_all_singletons(): void
    {
        $this->container->singleton('a', fn() => 'a_value');
        $this->container->singleton('b', fn() => 'b_value');

        $this->assertSame('a_value', $this->container->get('a'));
        $this->container->flush();
        $this->assertSame('a_value', $this->container->get('a')); // re-resolved
    }

    public function test_bind_overwrites_lazy(): void
    {
        $this->container->bindLazy('svc', fn() => 'lazy');
        $this->container->bind('svc', fn() => 'bound');

        $result = $this->container->get('svc');
        $this->assertSame('bound', $result);
    }
}
