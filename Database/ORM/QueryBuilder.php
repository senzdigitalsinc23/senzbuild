<?php
declare(strict_types=1);

namespace Database\ORM;

use App\Core\Database;
use PDO;
use RuntimeException;

class QueryBuilder
{
    protected string $table;
    protected string $alias = '';
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $whereHas = [];
    protected array $params = [];
    protected array $orderBy = [];
    protected array $groupBy = [];
    protected array $having = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $lock = null;
    protected array $with = [];
    protected string $modelClass;

    public function __construct(string $table, string $modelClass = Model::class)
    {
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function alias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function addSelect(string ...$columns): self
    {
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    public function join(string $table, string $first, string $operator = '=', string $second = '', string $type = 'INNER'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator = '=', string $second = ''): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator = '=', string $second = ''): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function where(string $column, mixed $value = null, string $operator = '=', string $boolean = 'AND'): self
    {
        if ($value === null && !str_contains($operator, ' ')) {
            $operator = '=';
            $value = $column;
            $column = '1';
        }

        $param = 'w_' . count($this->params);
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
            'param' => $param,
        ];
        $this->params[$param] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        return $this->where($column, $value, $operator, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $params = [];
        foreach ($values as $i => $value) {
            $param = "win_{$i}_" . count($this->params);
            $params[$param] = $value;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'params' => $params,
            'boolean' => $boolean,
        ];
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $where = $this->whereIn($column, $values);
        $where->wheres[count($where->wheres) - 1]['not'] = true;
        return $where;
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => false,
        ];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => true,
        ];
        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): self
    {
        $param1 = 'wbet_' . count($this->params);
        $param2 = 'wbet2_' . count($this->params);
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $boolean,
            'param1' => $param1,
            'param2' => $param2,
        ];
        $this->params[$param1] = $min;
        $this->params[$param2] = $max;

        return $this;
    }

    public function whereHas(string $relation, callable $callback, string $boolean = 'AND'): self
    {
        $this->whereHas[] = compact('relation', 'callback', 'boolean');
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $param = 'h_' . count($this->params);
        $this->having[] = compact('column', 'operator', 'value', 'param');
        $this->params[$param] = $value;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function forUpdate(): self
    {
        $this->lock = 'FOR UPDATE';
        return $this;
    }

    public function sharedLock(): self
    {
        $this->lock = 'LOCK IN SHARE MODE';
        return $this;
    }

    public function with(string ...$relations): self
    {
        $this->with = array_merge($this->with, $relations);
        return $this;
    }

    /**
     * Remove the default deleted_at IS NULL scope (include trashed records).
     */
    public function withTrashedScope(): self
    {
        // Remove any existing deleted_at IS NULL condition
        $this->wheres = array_values(array_filter($this->wheres, function ($w) {
            return !($w['type'] === 'basic'
                && $w['column'] === 'deleted_at'
                && $w['operator'] === '='
                && $w['value'] === null);
        }));
        return $this;
    }

    public function toSql(): string
    {
        $table = $this->alias ? "{$this->table} AS {$this->alias}" : $this->table;
        $columns = empty($this->columns) ? '*' : implode(', ', $this->columns);
        $sql = "SELECT {$columns} FROM {$table}";

        foreach ($this->joins as $join) {
            $type = $join['type'];
            $sql .= " {$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        $whereClauses = $this->buildWhereClauses();
        if ($whereClauses !== '') {
            $sql .= " WHERE {$whereClauses}";
        }

        if ($this->groupBy) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        if ($this->having) {
            $clauses = [];
            foreach ($this->having as $h) {
                $clauses[] = "{$h['column']} {$h['operator']} :{$h['param']}";
            }
            $sql .= " HAVING " . implode(' AND ', $clauses);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        if ($this->lock) {
            $sql .= " {$this->lock}";
        }

        return $sql;
    }

    protected function buildWhereClauses(): string
    {
        if (empty($this->wheres) && empty($this->whereHas)) {
            return '';
        }

        $clauses = [];
        $whereHasIndex = 0;

        foreach ($this->wheres as $i => $where) {
            $prefix = $i === 0 ? '' : " {$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = "{$prefix}{$where['column']} {$where['operator']} :{$where['param']}";
                    break;

                case 'in':
                    $placeholders = implode(', ', array_map(fn($p) => ":{$p}", array_keys($where['params'])));
                    $not = !empty($where['not']) ? 'NOT ' : '';
                    $clauses[] = "{$prefix}{$where['column']} {$not}IN ({$placeholders})";
                    break;

                case 'null':
                    $not = !empty($where['not']) ? 'NOT ' : '';
                    $clauses[] = "{$prefix}{$where['column']} IS {$not}NULL";
                    break;

                case 'between':
                    $clauses[] = "{$prefix}{$where['column']} BETWEEN :{$where['param1']} AND :{$where['param2']}";
                    break;
            }
        }

        foreach ($this->whereHas as $whereHas) {
            $prefix = empty($clauses) ? '' : " {$whereHas['boolean']} ";

            // Resolve relation metadata from Model
            $model = new $this->modelClass();
            $relation = $model->{$whereHas['relation']}();

            if (!$relation instanceof Relation) {
                throw new RuntimeException("Relation {$whereHas['relation']} must return a Relation object.");
            }

            $relatedTable = $relation->relatedModel::$table;
            $foreignKey = $relation->foreignKey;
            $localKey = $relation->localKey;

            // Build subquery
            $subQb = new QueryBuilder($relatedTable, $relation->relatedModel);

            // Add the basic relation join condition
            if ($relation->type === 'belongsTo') {
                // For belongsTo, the localKey is the PK of the related table,
                // and foreignKey is the FK on the current table.
                $subQb->where($localKey, $this->table . '.' . $foreignKey);
            } else {
                $subQb->where($foreignKey, $this->table . '.' . $localKey);
            }

            // Apply callback if provided
            if ($whereHas['callback']) {
                ($whereHas['callback'])($subQb);
            }

            // Merge sub-query parameters into main params
            foreach ($subQb->params as $param => $value) {
                $this->params[$param] = $value;
            }

            $subSql = $subQb->toSql();
            // Remove SELECT * from subquery to use SELECT 1 for performance
            $subSql = preg_replace('/^SELECT \*\s+FROM/', 'SELECT 1 FROM', $subSql);

            $clauses[] = "{$prefix}EXISTS ({$subSql})";
        }

        return ltrim(implode('', $clauses));
    }

    public function get(): array
    {
        $db = Database::getInstance()->getConnection();
        $sql = $this->toSql();
        $stmt = $db->prepare($sql);
        $stmt->execute($this->params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = array_map(fn($row) => new $this->modelClass($row), $rows);

        if ($this->with) {
            $results = $this->eagerLoad($results);
        }

        return $results;
    }

    public function first(): ?object
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $total = $this->count();

        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $items = $this->get();

        return [
            'data' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / $perPage),
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    public function count(): int
    {
        $db = Database::getInstance()->getConnection();
        $columns = $this->columns;
        $this->columns = ['COUNT(*) as aggregate'];
        $sql = $this->toSql();
        $this->columns = $columns;

        $stmt = $db->prepare($sql);
        $stmt->execute($this->params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['aggregate'];
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function chunk(int $size, callable $callback): void
    {
        $page = 1;
        do {
            $results = $this->paginate($size, $page);
            if (empty($results['data'])) {
                break;
            }
            $callback($results['data'], $page);
            $page++;
        } while ($results['has_more']);
    }

    public function insert(array $data): bool
    {
        $db = Database::getInstance()->getConnection();
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $db->prepare($sql);
        return $stmt->execute($data);
    }

    public function update(array $data): int
    {
        $db = Database::getInstance()->getConnection();
        $sets = [];
        $params = $this->params;

        foreach ($data as $column => $value) {
            $param = 'u_' . str_replace('.', '_', $column) . '_' . count($params);
            $sets[] = "{$column} = :{$param}";
            $params[$param] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $whereClauses = $this->buildWhereClauses();
        if ($whereClauses !== '') {
            $sql .= " WHERE {$whereClauses}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $db = Database::getInstance()->getConnection();
        $sql = "DELETE FROM {$this->table}";
        $whereClauses = $this->buildWhereClauses();
        if ($whereClauses !== '') {
            $sql .= " WHERE {$whereClauses}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->rowCount();
    }

    public function pluck(string $column): array
    {
        $db = Database::getInstance()->getConnection();
        $this->columns = [$column];
        $sql = $this->toSql();
        $this->columns = ['*'];

        $stmt = $db->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function value(string $column): mixed
    {
        $result = $this->first();
        return $result ? $result->{$column} : null;
    }

    public function max(string $column): mixed
    {
        return $this->aggregate("MAX({$column})");
    }

    public function min(string $column): mixed
    {
        return $this->aggregate("MIN({$column})");
    }

    public function avg(string $column): float
    {
        return (float)$this->aggregate("AVG({$column})");
    }

    public function sum(string $column): float
    {
        return (float)$this->aggregate("SUM({$column})");
    }

    protected function aggregate(string $expression): mixed
    {
        $db = Database::getInstance()->getConnection();
        $columns = $this->columns;
        $this->columns = ["{$expression} as aggregate"];
        $sql = $this->toSql();
        $this->columns = $columns;

        $stmt = $db->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['aggregate'];
    }

    protected function eagerLoad(array $models): array
    {
        if (empty($models)) {
            return $models;
        }

        foreach ($this->with as $relationName) {
            if (method_exists($this->modelClass, $relationName)) {
                $instance = new $this->modelClass();
                $relation = $instance->{$relationName}();

                if (!$relation instanceof Relation) {
                    continue;
                }

                $foreignKey = $relation->foreignKey;
                $localKey = $relation->localKey;

                // Get primary keys from the parent models
                $ids = array_map(fn($m) => $m->getId(), array_filter($models, fn($m) => $m->getId() !== null));
                $ids = array_unique(array_filter($ids));

                if (empty($ids)) {
                    continue;
                }

                // Fetch related records using a fresh query builder
                $relatedQb = new QueryBuilder($relation->relatedModel::$table, $relation->relatedModel);

                if ($relation->type === 'belongsTo') {
                    // For belongsTo, we search for the localKey in the related table where it matches
                    // the foreignKey value in our models.
                    $foreignValues = array_map(fn($m) => $m->attributes[$relation->foreignKey] ?? null, $models);
                    $foreignValues = array_unique(array_filter($foreignValues));

                    if (empty($foreignValues)) continue;

                    $relatedResults = $relatedQb->whereIn($localKey, $foreignValues)->get();

                    $grouped = [];
                    foreach ($relatedResults as $rel) {
                        $grouped[$rel->getId()] = $rel;
                    }

                    foreach ($models as $model) {
                        $fkValue = $model->attributes[$relation->foreignKey] ?? null;
                        $model->{$relationName} = $grouped[$fkValue] ?? null;
                    }
                } else {
                    // hasOne, hasMany, belongsToMany
                    $relatedResults = $relatedQb->whereIn($foreignKey, $ids)->get();

                    $grouped = [];
                    foreach ($relatedResults as $rel) {
                        $fk = $rel->attributes[$foreignKey] ?? null;
                        if ($fk) {
                            $grouped[$fk][] = $rel;
                        }
                    }

                    foreach ($models as $model) {
                        $id = $model->getId();
                        $results = $grouped[$id] ?? [];

                        if ($relation->type === 'hasOne') {
                            $model->{$relationName} = $results[0] ?? null;
                        } else {
                            $model->{$relationName} = $results;
                        }
                    }
                }
            }
        }

        return $models;
    }
}