<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Permission Policy / Gate — RBAC + ABAC authorization system.
 *
 * Usage:
 *   Gate::define('delete-post', function(User $user, Post $post) {
 *       return $user->id === $post->author_id;
 *   });
 *
 *   Gate::authorize('delete-post', $post);  // throws AuthorizationException if denied
 *   Gate::allows('delete-post', $post);    // returns bool
 *
 *   // Policy classes:
 *   class PostPolicy {
 *       public function update(User $user, Post $post): bool {
 *           return $user->id === $post->author_id;
 *       }
 *   }
 *   Gate::policy(Post::class, PostPolicy::class);
 *   Gate::authorize('update', $post); // resolves to PostPolicy@update
 */
class Gate
{
    protected static array $abilities = [];
    protected static array $policies = [];

    /**
     * Define a named ability (closure).
     *
     * @param string $name Ability name
     * @param callable $callback
     */
    public static function define(string $name, callable $callback): void
    {
        self::$abilities[$name] = $callback;
    }

    /**
     * Register a model policy class.
     *
     * @param string $modelClass Model class name
     * @param string $policyClass Policy class name
     */
    public static function policy(string $modelClass, string $policyClass): void
    {
        self::$policies[$modelClass] = $policyClass;
    }

    /**
     * Check if an ability is allowed.
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    public static function allows(string $ability, mixed ...$arguments): bool
    {
        try {
            self::authorize($ability, ...$arguments);
            return true;
        } catch (AuthorizationException $e) {
            return false;
        }
    }

    /**
     * Authorize an ability or throw.
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return mixed Result of the ability callback
     * @throws AuthorizationException
     */
    public static function authorize(string $ability, mixed ...$arguments): mixed
    {
        // Check for policy-based authorization (ABAC)
        $firstArg = $arguments[0] ?? null;
        if ($firstArg !== null) {
            $modelClass = $firstArg instanceof \Throwable ? null : ($firstArg::class ?? null);
            if ($modelClass !== null && isset(self::$policies[$modelClass])) {
                $policyClass = self::$policies[$modelClass];
                $policy = new $policyClass();
                if (method_exists($policy, $ability)) {
                    $result = $policy->$ability(...$arguments);
                    if ($result === false) {
                        throw new AuthorizationException("Action '{$ability}' is not allowed for this resource.");
                    }
                    return $result;
                }
            }
        }

        // Check named ability
        $callback = self::$abilities[$ability] ?? null;
        if ($callback === null) {
            throw new AuthorizationException("Authorization ability '{$ability}' is not defined.");
        }

        $result = $callback(...$arguments);
        if ($result === false) {
            throw new AuthorizationException("Action '{$ability}' is not allowed.");
        }
        return $result;
    }

    /**
     * Check if any of the given abilities are allowed.
     */
    public static function any(string $anyOf, mixed ...$arguments): bool
    {
        foreach (explode('|', $anyOf) as $ability) {
            if (self::allows(trim($ability), ...$arguments)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if all of the given abilities are allowed.
     */
    public static function none(string $noneOf, mixed ...$arguments): bool
    {
        return !self::any($noneOf, ...$arguments);
    }

    /**
     * Get all defined abilities.
     */
    public static function abilities(): array
    {
        return array_keys(self::$abilities);
    }

    /**
     * Reset all gates and policies.
     */
    public static function flush(): void
    {
        self::$abilities = [];
        self::$policies = [];
    }
}

/**
 * Authorization Exception — thrown when gate denies access.
 */
class AuthorizationException extends \Exception
{
}

/**
 * Permission Middleware — enforces gate checks on routes.
 */
class PermissionMiddleware implements MiddlewareInterface
{
    protected string $ability;
    protected array $params;

    public function __construct(string $ability, array $params = [])
    {
        $this->ability = $ability;
        $this->params = $params;
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            $response->setStatusCode(401);
            $response->json(['success' => false, 'message' => 'Unauthenticated']);
            return $response;
        }

        try {
            Gate::authorize($this->ability, ...array_merge([$user], $this->params));
        } catch (AuthorizationException $e) {
            $response->setStatusCode(403);
            $response->json(['success' => false, 'message' => 'Forbidden: ' . $e->getMessage()]);
            return $response;
        }

        return $next($request, $response);
    }
}
