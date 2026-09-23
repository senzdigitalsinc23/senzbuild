<?php
declare(strict_types=1);

namespace Tests\Factory;

/**
 * Base factory for model data generation.
 *
 * Usage:
 *   class UserFactory extends Factory
 *   {
 *       protected string $model = User::class;
 *
 *       public function definition(): array
 *       {
 *           return [
 *               'name'  => $this->faker->name,
 *               'email' => $this->faker->unique()->safeEmail,
 *               'password' => password_hash('password', PASSWORD_BCRYPT),
 *           ];
 *       }
 *   }
 *
 *   // Create a single instance
 *   $user = UserFactory::new()->create();
 *
 *   // Create multiple instances
 *   $users = UserFactory::new()->count(5)->create();
 *
 *   // With state overrides
 *   $user = UserFactory::new()->state(['email' => 'admin@example.com'])->create();
 */
abstract class Factory
{
    /**
     * The model class this factory creates.
     * @var string
     */
    protected string $model;

    /**
     * Number of instances to create.
     * @var int
     */
    protected int $count = 1;

    /**
     * Additional state to merge into definitions.
     * @var array<string, mixed>
     */
    protected array $state = [];

    /**
     * Faker instance for data generation.
     * @var \Faker\Factory|\Faker\Generator
     */
    protected $faker;

    /**
     * Create a new factory instance.
     */
    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Create a new factory instance.
     *
     * @return static
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Set the number of instances to create.
     *
     * @param int $count
     * @return self
     */
    public function count(int $count): self
    {
        $this->count = max(1, $count);
        return $this;
    }

    /**
     * Set additional state to merge into definitions.
     *
     * @param array<string, mixed> $state
     * @return self
     */
    public function state(array $state): self
    {
        $this->state = array_merge($this->state, $state);
        return $this;
    }

    /**
     * Define the default attributes for the factory.
     * Override in child class.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;

    /**
     * Create instances and persist them.
     *
     * @return array<\Database\ORM\Model>|\Database\ORM\Model
     */
    public function create(): array|\Database\ORM\Model
    {
        $results = [];
        for ($i = 0; $i < $this->count; $i++) {
            $data = $this->definition();
            $data = array_merge($data, $this->state);
            $results[] = $this->makeInstance($data);
        }

        if ($this->count === 1) {
            return $results[0] ?? null;
        }
        return $results;
    }

    /**
     * Create instances without persisting.
     *
     * @return array<array<string, mixed>>
     */
    public function make(): array
    {
        $results = [];
        for ($i = 0; $i < $this->count; $i++) {
            $data = $this->definition();
            $data = array_merge($data, $this->state);
            $results[] = $data;
        }
        return $this->count === 1 ? ($results[0] ?? []) : $results;
    }

    /**
     * Create a single instance without persisting.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        $data = $this->definition();
        return array_merge($data, $this->state);
    }

    /**
     * Create an instance and persist it to the database.
     *
     * @param array<string, mixed> $data
     * @return \Database\ORM\Model
     */
    protected function makeInstance(array $data): \Database\ORM\Model
    {
        $modelClass = $this->model;
        return $modelClass::createSafe($data);
    }

    /**
     * Get the Faker instance.
     *
     * @return \Faker\Factory|\Faker\Generator
     */
    public function getFaker()
    {
        return $this->faker;
    }
}

/**
 * Fake implementations for testing.
 */
class QueueFake
{
    protected static array $jobs = [];
    protected static bool $afterCommit = false;

    public static function push($job): void
    {
        self::$jobs[] = $job;
    }

    public static function fake(): void
    {
        self::$jobs = [];
    }

    public static function assertPushed($job = null, callable $callback = null): void
    {
        if ($job === null) {
            return;
        }
        $found = false;
        foreach (self::$jobs as $queued) {
            if ($queued === $job || ($callback && $callback($queued))) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new \Exception("Expected job to be pushed but it was not.");
        }
    }

    public static function assertPushedOn($queue, $job = null): void
    {
        self::assertPushed($job);
    }

    public static function assertNothingPushed(): void
    {
        if (!empty(self::$jobs)) {
            throw new \Exception("Expected no jobs to be pushed but got " . count(self::$jobs) . ".");
        }
    }

    public static function pop($queue = null)
    {
        return array_shift(self::$jobs);
    }

    public static function clear(): void
    {
        self::$jobs = [];
    }
}

class CacheFake
{
    protected static array $store = [];
    protected static array $tags = [];

    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        self::$store[$key] = ['value' => $value, 'ttl' => $ttl, 'expires' => $ttl ? time() + $ttl : null];
        return true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!isset(self::$store[$key])) {
            return $default;
        }
        $entry = self::$store[$key];
        if ($entry['expires'] !== null && time() > $entry['expires']) {
            unset(self::$store[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public static function has(string $key): bool
    {
        return isset(self::$store[$key]);
    }

    public static function forget(string $key): bool
    {
        if (isset(self::$store[$key])) {
            unset(self::$store[$key]);
            return true;
        }
        return false;
    }

    public static function flush(): bool
    {
        self::$store = [];
        return true;
    }

    public static function clear(): void
    {
        self::$store = [];
    }

    public static function tag(string $name): TaggedCacheFake
    {
        return new TaggedCacheFake($name);
    }
}

class TaggedCacheFake
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        CacheFake::put("{$this->name}:{$key}", $value, $ttl);
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return CacheFake::get("{$this->name}:{$key}", $default);
    }

    public function forget(string $key): bool
    {
        return CacheFake::forget("{$this->name}:{$key}");
    }
}

class MailFake
{
    protected static array $sent = [];

    public static function send($mailable): void
    {
        self::$sent[] = $mailable;
    }

    public static function queue($mailable): void
    {
        self::$sent[] = ['queued' => true, 'mailable' => $mailable];
    }

    public static function assertSent($mailable, callable $callback = null): void
    {
        foreach (self::$sent as $sent) {
            $actual = is_array($sent) ? $sent['mailable'] : $sent;
            if ($actual === $mailable || ($callback && $callback($actual))) {
                return;
            }
        }
        throw new \Exception("Expected mailable to be sent but it was not.");
    }

    public static function assertNothingSent(): void
    {
        if (!empty(self::$sent)) {
            throw new \Exception("Expected no mails to be sent but got " . count(self::$sent) . ".");
        }
    }

    public static function clear(): void
    {
        self::$sent = [];
    }
}
