<?php

namespace App\Core;

class ORMQuery
{
    protected string $table;
    protected string $primaryKey;
    protected string $modelClass;
    protected array $conditions = [];

    public function __construct(string $table, string $primaryKey, string $modelClass, string $column, mixed $value)
    {
        $this->table = $table;
        $this->primaryKey = $primaryKey;
        $this->modelClass = $modelClass;
        $this->conditions[] = [$column, '=', $value];
    }

    public function where(string $column, mixed $value): self
    {
        $this->conditions[] = [$column, '=', $value];
        return $this;
    }

    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if ($this->conditions) {
            $clauses = [];
            foreach ($this->conditions as $i => [$col, $op, $val]) {
                $param = "param$i";
                $clauses[] = "$col $op :$param";
                $params[$param] = $val;
            }
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        $rows = Database::getInstance()->fetch($sql, $params);
        return array_map(fn($row) => new $this->modelClass($row), $rows);
    }

    public function first(): ?object
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if ($this->conditions) {
            $clauses = [];
            foreach ($this->conditions as $i => [$col, $op, $val]) {
                $param = "param$i";
                $clauses[] = "$col $op :$param";
                $params[$param] = $val;
            }
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        $sql .= " LIMIT 1";
        $row = Database::getInstance()->fetchSingle($sql, $params);
        return $row ? new $this->modelClass($row) : null;
    }
}
