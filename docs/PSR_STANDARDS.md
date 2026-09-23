PSR Standards Implemented

This framework now implements the following PSR standards:

PSR-11 Container Interface
- App\Core\Container implements Psr\Container\ContainerInterface
- Provides get($id) and has($id) methods for dependency injection

PSR-15 Middleware Interface
- App\Core\PsrMiddlewareAdapter wraps framework middleware as PSR-15 middleware
- Compatible with any PSR-7/PSR-15 stack

PSR-14 Event Dispatcher Interface
- App\Core\EventDispatcher implements Psr\EventDispatcher\EventDispatcherInterface
- Supports dispatching PHP objects as events (PSR-14 standard)
- Legacy string-based dispatch still available

PSR-16 Cache Interface
- App\Core\Cache implements Psr\Cache\CacheItemPoolInterface
- Provides getItem(), getItems(), hasItem(), save(), deleteItem(), clear()
- Backward compatible with existing Cache methods (get, set, forget, etc.)
- Includes CacheItem implementation

Dependencies Added
- psr/container (^2.0) - Already installed
- psr/http-server-middleware (^1.0) - Installed via composer
- psr/event-dispatcher (^1.0) - Installed via composer
- psr/cache (^3.0) - Installed via composer
