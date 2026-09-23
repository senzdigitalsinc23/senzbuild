# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **ResponseCacheMiddleware**: explicit nullable type for `$enabled` param
- **DocumentationController**: duplicate property declaration
- **View::__construct**: deprecation for `string $basePath = null` → `?string $basePath`
- **GraphQLServiceProvider**: moved to correct namespace `App\Providers`
- **Vault.php**: hex decoding fix for master key
- **phpunit.xml**: removed invalid `coverageThresholds` element
- **RouteCache.php**: fixed static constructor bug; added `forceCachePath()` for testing
- **Filesystem.php**: rewrote entirely; fixed path separator bug in `listContents()`
- **DrainMode.php**: fixed uninitialized static property `$signalFile`
- **EventBus.php**: fixed wildcard matching algorithm; null-safe Queue constructor
- **OAuth2Server.php**: added `introspect()` public method
- **StateMachine.php**: fixed null coalescing syntax error
- **docsController.php**: fixed duplicate property declaration
- **ApiResponse.php**: added `status_code` and `error` keys to responses; `paginated()` now accepts legacy `$meta` array signature
- **Router.php**: supports callable/closure actions and middleware; wraps `$next` for single-arg middleware callers
- **Request.php**: fixed `input()` to read `$_GET` at construction; `getAttribute()` checks custom attributes; added `getCustomAttribute()` alias; fixed `getUriPath()` for path string access
- **ExceptionHandlerTest.php**: fixed anonymous class return type declarations for PHPUnit 11 compatibility
- **OAuth2 Authorization Server** (`Core/OAuth2Server.php`) — authorization_code, client_credentials, password grant types
- **SAML 2.0 / LDAP SSO** (`Core/SamlServiceProvider.php`) — SP-initiated SAML flow + LDAP auth
- **Separate DB per Tenant** (`Core/TenantDatabase.php`) — tenant-aware connection pooling
- **OpenTelemetry Tracing** (`Core/Trace.php`) — span creation, trace context propagation, JSON export
- **Prometheus Metrics** (`Core/Metrics.php`) — counters, gauges, histograms, `/metrics` endpoint
- **Sentry Integration** (`Core/Sentry.php`) — exception tracking with fallback HTTP transport
- **Optimistic Locking** (`Core/OptimisticLock.php`) — `use OptimisticLock` trait with version column
- **Read/Write Replicas** (`Core/DatabaseManager.php`) — automatic read replica rotation
- **Database Sharding** (`Core/ShardingDatabase.php`) — shard-aware routing
- **Model Binding** (`Core/ModelBinder.php`) — auto-resolve route params to model instances
- **State Machine** (`Core/StateMachine.php`) — configurable state transitions with registry
- **Event Bus** (`Core/EventBus.php`) — wildcard routing + event replay on top of PSR-14
- **Permission Gates/Policies** (`Core/Gate.php`) — RBAC + ABAC with `Gate::authorize()`
- **Password Policy** (`Core/PasswordPolicy.php`) — complexity rules, common password rejection
- **i18n / Localization** (`Core/Locale.php`) — translation layer with fallback, pluralization, timezone
- **Maintenance Mode** (`Core/MaintenanceMode.php`) — 503 with bypass URIs
- **Zero-Downtime Drain** (`Core/DrainMode.php`) — graceful queue worker shutdown
- **Job Batching** (`Core/JobBatch.php`) — grouped job execution with progress tracking
- **Redis Queue Driver** (`Core/RedisQueue.php`) — Redis-backed queue via phpredis/predis
- **Array Queue Driver** (`Core/ArrayQueue.php`) — in-memory queue for testing
- **Route Cache** (`Core/RouteCache.php`) — compiled route cache for fast dispatch
- **View Cache** (`Core/ViewCache.php`) — compiled Blade-like template cache
- **Tinker REPL** (`Core/Tinker.php`) — interactive PHP shell (`php bin/console tinker`)
- **libsodium Encryption** (`Core/SodiumCrypto.php`) — secretbox, sealed box, PBKDF2 key derivation
- **Queueable Mail** (`Core/QueueableMail.php`) — email queued through job system
- **Multi-Channel Notifications** (`Core/Notification.php`) — mail, SMS, Slack, DB channels
- **`/readiness` Endpoint** — K8s-compatible readiness probe (separate from `/health`)
- **Config Cache** (`Core/ConfigCache.php`) — merged config into single PHP file
- **Encrypted Secrets Vault** (`Core/Vault.php`) — AES-256-CBC encrypted .env secrets
- **Secret Key Rotation** (`Core/KeyManager.php`) — JWT key rotation with history
- **Argon2id Hashing** (`Core/Hash.php`) — bcrypt + argon2id with cost detection
- **Mass Assignment Protection** — `$fillable` / `$guarded` on all models
- **ETag Middleware** (`Core/ETagMiddleware.php`) — HTTP caching with XXH128
- **API Resource Layer** (`Core/Resource.php`) — sparse fieldsets via `?fields=`
- **N+1 Query Detector** (`Core/QueryDetector.php`) — debug-mode query tracking
- **Factories & Fakes** — `tests/Factory/`, `QueueFake`, `CacheFake`, `MailFake`

### Fixed
- `ResponseCacheMiddleware`: explicit nullable type for `$enabled` param
- `DocumentationController`: duplicate property declaration
- `View::__construct`: deprecation for `string $basePath = null` → `?string $basePath`
- `GraphQLServiceProvider`: moved to correct namespace `App\Providers`
- `Vault.php`: hex decoding fix for master key

---