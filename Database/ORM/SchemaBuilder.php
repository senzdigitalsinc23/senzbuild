<?php
namespace Database\ORM;

class SchemaBuilder
{
    public function create(string $table, callable $callback): void
    {
        echo "Creating table: {$table}\n";
        $blueprint = new TableBlueprint($table);
        $callback($blueprint);
        $sql = $blueprint->toSql();
        // Here you would run the generated SQL using your DB connection
        echo $sql . "\n"; // just demo output
    }

    public function table(string $table, callable $callback): void
    {
        echo "Modifying table: {$table}\n";
        $blueprint = new TableBlueprint($table);
        $callback($blueprint);
        $sql = $blueprint->toSql();
        // Run the SQL to modify table
        echo $sql . "\n";
    }

    public function drop(string $table): void
    {
        echo "Dropping table: {$table}\n";
        // Run DROP TABLE SQL
        // e.g. $this->db->exec("DROP TABLE IF EXISTS {$table}");
    }
}
