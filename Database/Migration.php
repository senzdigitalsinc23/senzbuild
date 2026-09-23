<?php
declare(strict_types=1);

namespace Database;

use PDO;
use App\Core\Database;
use Database\ORM\SchemaBuilder;

abstract class Migration
{
    protected PDO $db;
    protected $schema;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->schema = new SchemaBuilder($db);
    }

    abstract public function up(): void;
    abstract public function down(): void;

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
