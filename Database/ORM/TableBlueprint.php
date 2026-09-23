<?php
namespace Database\ORM;

class TableBlueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $primaryKeys = [];
    protected array $uniqueKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(): self
    {
        $this->columns[] = "`id` INT AUTO_INCREMENT";
        $this->primaryKeys[] = "id";
        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "`$name` VARCHAR($length)";
        return $this;
    }

    public function text(string $name): self
    {
        $this->columns[] = "`$name` TEXT";
        return $this;
    }

    public function integer(string $name, bool $nullable = false): self
    {
        $col = "`$name` INT";
        if ($nullable) {
            $col .= " NULL";
        } else {
            $col .= " NOT NULL";
        }
        $this->columns[] = $col;
        return $this;
    }

    public function unique(string $column): self
    {
        $this->uniqueKeys[] = $column;
        return $this;
    }

    public function primary(array $columns): self
    {
        $this->primaryKeys = $columns;
        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = "`created_at` TIMESTAMP NULL DEFAULT NULL";
        $this->columns[] = "`updated_at` TIMESTAMP NULL DEFAULT NULL";
        return $this;
    }

    public function toSql(): string
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (\n";
        $sql .= "  " . implode(",\n  ", $this->columns);

        if (!empty($this->primaryKeys)) {
            $pk = implode(", ", array_map(fn($c) => "`$c`", $this->primaryKeys));
            $sql .= ",\n  PRIMARY KEY ({$pk})";
        }

        if (!empty($this->uniqueKeys)) {
            foreach ($this->uniqueKeys as $uk) {
                $sql .= ",\n  UNIQUE KEY `unique_{$uk}` (`{$uk}`)";
            }
        }

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        return $sql;
    }
}
