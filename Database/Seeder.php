<?php

namespace Database;

use PDO;

abstract class Seeder
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    abstract public function run(): void;

    /**
     * Executes an SQL query, optionally with parameters for prepared statements.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters for prepared statements.
     * @return void
     */
    public function execute(string $sql, array $params = []): void
    {
        if (empty($params)) {
            $this->db->exec($sql);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }
}
