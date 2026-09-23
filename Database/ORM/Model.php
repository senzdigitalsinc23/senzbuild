<?php

namespace Database\ORM;

use App\Core\Database;
use App\Core\Request;
use App\Models\User;
use PDO;

abstract class Model
{
    protected static string $table;
    protected array $attributes = [];
    protected array $relations = [];
    protected PDO $db;

    /**
     * Whether this model uses soft deletes.
     * Set to true in child models to enable soft-delete behavior.
     */
    protected static bool $softDelete = false;

    /**
     * The attributes that are mass assignable.
     * If empty, all attributes except those in $guarded are fillable.
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * The attributes that are not mass assignable.
     * @var array<string>
     */
    protected array $guarded = ['id'];

    /**
     * The model's original attributes (for dirty detection).
     * @var array<string, mixed>
     */
    protected array $original = [];

    public function __construct(array $attributes = [])
    {
        if (!empty($attributes)) {
            $this->fill($attributes);
        }
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $result = $this->$key();
            if ($result instanceof Relation) {
                return $this->relations[$key] = $this->resolveRelation($result);
            }
            return $this->relations[$key] = $result;
        }

        return $this->attributes[$key] ?? null;
    }

    /**
     * Mass-assign attributes respecting $fillable / $guarded.
     *
     * @param array<string, mixed> $attributes
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * Set an attribute, checking mass-assignment rules.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->isGuarded($key)) {
            return;
        }
        if (!$this->isFillable($key)) {
            return;
        }
        $this->attributes[$key] = $value;
    }

    /**
     * Check if an attribute is guarded from mass assignment.
     *
     * @param string $key
     * @return bool
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded, true);
    }

    /**
     * Check if an attribute is fillable.
     * If $fillable is non-empty, only those keys are allowed.
     *
     * @param string $key
     * @return bool
     */
    public function isFillable(string $key): bool
    {
        if (empty($this->fillable)) {
            // Empty fillable means everything NOT in guarded is fillable
            return !$this->isGuarded($key);
        }
        return in_array($key, $this->fillable, true);
    }

    /**
     * Create a new model instance from an array, with mass-assignment protection.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public static function createSafe(array $data): static
    {
        $instance = new static();
        $instance->fill($data);
        return $instance;
    }

    /**
     * Get the fillable attributes for this model.
     *
     * @return array<string>
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Get the guarded attributes for this model.
     *
     * @return array<string>
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    protected function resolveRelation(Relation $relation): mixed
    {
        $qb = new QueryBuilder($relation->relatedModel::$table, $relation->relatedModel);

        switch ($relation->type) {
            case 'hasOne':
            case 'hasMany':
                $qb->where($relation->foreignKey, $this->getId());
                break;
            case 'belongsTo':
                $foreignValue = $this->attributes[$relation->foreignKey] ?? null;
                if ($foreignValue === null) return null;
                $qb->where($relation->localKey, $foreignValue);
                break;
            case 'belongsToMany':
                $pivotTable = $relation->pivotTable;
                $qb->join($pivotTable, $relation->relatedKey, '=', $relation->foreignKey);
                $qb->where($relation->foreignKey, $this->getId());
                break;
        }

        if ($relation->callback) {
            $relation->callback($qb);
        }

        return $relation->type === 'hasMany' || $relation->type === 'belongsToMany'
            ? $qb->get()
            : $qb->first();
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function getId(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    public function toArray(): array
    {
        return array_merge($this->attributes, $this->relations);
    }

    public static function query(): QueryBuilder
    {
        // Switch to tenant database if applicable
        $tenantId = \App\Core\TenantMiddleware::getCurrentTenantId();
        if ($tenantId !== null && \App\Core\TenantDatabase::hasTenant($tenantId)) {
            \App\Core\TenantDatabase::connection($tenantId);
        }

        $qb = new QueryBuilder(static::$table, static::class);
        if (static::$softDelete) {
            $qb->where('deleted_at', null);
        }
        return $qb;
    }

    public static function all($newTable = '')
    {
        $table = $newTable != '' ? $newTable : static::$table;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        return static::query()->get();
    }

    public static function select($fields = [], $newTable = '')
    {
        $table = $newTable != '' ? $newTable : static::$table;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        return static::query()->select($fields)->get();
    }

    public static function find(int $id): ?static
    {
        $table = static::$table;
        $db = Database::getInstance()->getConnection();

        $sql = "SELECT * FROM {$table} WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetchObject(static::class);

        if ($result && method_exists($result, 'syncOriginal')) {
            $result->syncOriginal();
        }

        return $result ?: null;
    }

    public function save(): bool
    {
        // Encrypt before persisting
        if (method_exists($this, 'preSave')) {
            $this->preSave();
        }

        $db = Database::getInstance()->getConnection();
        $id = $this->getId();

        if ($id) {
            // Update - only save fillable attributes
            $filtered = [];
            foreach ($this->attributes as $key => $value) {
                if (!$this->isGuarded($key) && $this->isFillable($key)) {
                    $filtered[$key] = $value;
                }
            }

            if (method_exists($this, 'auditChanges')) {
                $this->auditChanges();
            }

            $fields = array_map(fn($k) => "`$k` = :$k", array_keys($filtered));
            $sql = "UPDATE " . static::$table . " SET " . implode(', ', $fields) . " WHERE id = :id";
            $params = array_merge($filtered, ['id' => $id]);
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Insert - only insert fillable attributes
            $filtered = [];
            foreach ($this->attributes as $key => $value) {
                if (!$this->isGuarded($key) && $this->isFillable($key)) {
                    $filtered[$key] = $value;
                }
            }

            $columns = array_keys($filtered);
            $placeholders = ":" . implode(",:", $columns);
            $sql = "INSERT INTO " . static::$table . " (" . implode(',', $columns) . ") VALUES ($placeholders)";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($filtered);
            if ($success) {
                $this->attributes['id'] = (int)$db->lastInsertId();
                if (method_exists($this, 'syncOriginal')) {
                    $this->syncOriginal();
                }
            }
            return $success;
        }
    }

    public static function where(string $column, $value, $newTable = '')
    {
        $table = $newTable != '' ? $newTable : static::$table;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name');
        }

        $db = Database::getInstance()->getConnection();

        $sql = "SELECT * FROM {$table} WHERE {$column} = :value LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['value' => $value]);
        $result = $stmt->fetchObject(static::class);

        return $result ?: null;
    }

    public static function create(array $data, $newTable = "")
    {
        $table = $newTable != '' ? $newTable : static::$table;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        // Apply mass-assignment protection
        $instance = new static();
        $filtered = [];
        foreach ($data as $key => $value) {
            if (!$instance->isGuarded($key) && $instance->isFillable($key)) {
                $filtered[$key] = $value;
            }
        }

        $db = Database::getInstance()->getConnection();

        $columns = array_filter(array_keys($filtered), function ($col) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                throw new \InvalidArgumentException("Invalid column name: $col");
            }
            return true;
        });

        $columnList = implode(",", $columns);
        $placeholders = ":" . implode(",:", $columns);

        $sql = "INSERT INTO $table ($columnList) VALUES ($placeholders)";

        $stmt = $db->prepare($sql);
        $stmt->execute($filtered);

        $id = (int)$db->lastInsertId();
        return static::find($id);
    }

    // Relation helpers

    protected function hasOne(string $related, string $foreignKey, string $localKey = 'id'): Relation
    {
        return new Relation($related, $foreignKey, $localKey, 'hasOne');
    }

    protected function hasMany(string $related, string $foreignKey, string $localKey = 'id'): Relation
    {
        return new Relation($related, $foreignKey, $localKey, 'hasMany');
    }

    protected function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): Relation
    {
        return new Relation($related, $foreignKey, $ownerKey, 'belongsTo');
    }

    protected function belongsToMany(string $related, string $pivotTable, string $foreignKey, string $relatedKey): Relation
    {
        return new Relation($related, $foreignKey, 'id', 'belongsToMany', $pivotTable, $relatedKey);
    }

    public static function paginate($limit = 10, $offset = 0, $orderBy = '', $order = 'ASC')
    {
        $qb = static::query();
        $page = ($offset / max(1, $limit)) + 1;
        return $qb->paginate((int)$limit, (int)$page);
    }

    public static function countAll()
    {
        return static::query()->count();
    }

    // ── Soft delete helpers ──────────────────────────────────────────────────

    /**
     * Enable soft deletes on this model.
     */
    public static function useSoftDelete(): void
    {
        static::$softDelete = true;
    }

    /**
     * Soft-delete this model instance (sets deleted_at).
     */
    public function delete(): bool
    {
        if (static::$softDelete) {
            return static::query()->where('id', $this->getId())->update([
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
        }
        return static::query()->where('id', $this->getId())->delete() > 0;
    }

    /**
     * Restore a soft-deleted model instance.
     */
    public function restore(): bool
    {
        if (!static::$softDelete) {
            return false;
        }
        return static::withTrashed()->where('id', $this->getId())->update([
            'deleted_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    /**
     * Include soft-deleted records in the query.
     */
    public static function withTrashed(): QueryBuilder
    {
        $qb = new QueryBuilder(static::$table, static::class);
        if (static::$softDelete) {
            $qb->withTrashedScope();
        }
        return $qb;
    }

    /**
     * Count only soft-deleted records.
     */
    public static function trashedCount(): int
    {
        if (!static::$softDelete) {
            return 0;
        }
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM `" . static::$table . "` WHERE deleted_at IS NOT NULL");
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    }
}
