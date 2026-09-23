<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Model Versioning — automatic version history for any model.
 *
 * Add `use ModelVersioning` trait to a model. Each save creates a version record.
 *
 * Config:
 *   protected static int $maxVersions = 100;  // keep last 100 versions
 *
 * Usage:
 *   $product = Product::find(1);
 *   $product->price = 99;
 *   $product->save();                          // creates version record
 *   $versions = $product->getVersions();       // all historical versions
 *   $product->revertToVersion(3);              // restore version #3
 */
trait ModelVersioning
{
    protected static int $maxVersions = 100;

    /**
     * Override save() to create version records.
     */
    public function save(): bool
    {
        $id = $this->getId();
        $saved = parent::save();

        if ($saved && $id !== null) {
            $this->createVersion($id);
        }

        return $saved;
    }

    /**
     * Create a version snapshot after save.
     */
    protected function createVersion(int $id): void
    {
        $db = Database::getInstance()->getConnection();
        $table = static::$table . '_versions';

        // Create table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS {$table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_id INT NOT NULL,
            model_class VARCHAR(255) NOT NULL,
            snapshot TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_model (model_id, model_class)
        )");

        $snapshot = json_encode([
            'attributes' => $this->attributes,
            'version'    => $this->attributes['version'] ?? 1,
        ]);

        $db->query(
            "INSERT INTO {$table} (model_id, model_class, snapshot) VALUES (:id, :class, :snapshot)",
            [
                ':id'      => $id,
                ':class'   => static::class,
                ':snapshot'=> $snapshot,
            ]
        );

        // Prune old versions
        $this->pruneVersions($id);
    }

    /**
     * Get version history for this model instance.
     *
     * @return array<int, array>
     */
    public function getVersions(): array
    {
        $id = $this->getId();
        if ($id === null) {
            return [];
        }

        $db = Database::getInstance()->getConnection();
        $results = $db->fetch(
            "SELECT id, snapshot, created_at FROM " . static::$table . "_versions
             WHERE model_id = :id AND model_class = :class
             ORDER BY id DESC LIMIT " . static::$maxVersions,
            ['id' => $id, 'class' => static::class]
        );

        foreach ($results as &$row) {
            $row['data'] = json_decode($row['snapshot'], true);
        }
        return $results;
    }

    /**
     * Revert to a specific version.
     */
    public function revertToVersion(int $versionId): bool
    {
        $db = Database::getInstance()->getConnection();
        $row = $db->fetchSingle(
            "SELECT snapshot FROM " . static::$table . "_versions WHERE id = :id LIMIT 1",
            ['id' => $versionId]
        );

        if ($row === null) {
            return false;
        }

        $data = json_decode($row['snapshot'], true);
        if ($data === null || !isset($data['attributes'])) {
            return false;
        }

        foreach ($data['attributes'] as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this->save();
    }

    /**
     * Remove old versions beyond max.
     */
    protected function pruneVersions(int $id): void
    {
        $db = Database::getInstance()->getConnection();
        $db->query(
            "DELETE FROM " . static::$table . "_versions
             WHERE model_id = :id AND model_class = :class
             AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM " . static::$table . "_versions
                     WHERE model_id = :id AND model_class = :class
                     ORDER BY id DESC LIMIT " . static::$maxVersions . "
                 ) AS kept
             )",
            ['id' => $id, 'class' => static::class]
        );
    }

    /**
     * Delete all version history for a model instance.
     */
    public function deleteVersions(): void
    {
        $id = $this->getId();
        if ($id === null) {
            return;
        }
        $db = Database::getInstance()->getConnection();
        $db->query(
            "DELETE FROM " . static::$table . "_versions
             WHERE model_id = :id AND model_class = :class",
            ['id' => $id, 'class' => static::class]
        );
    }
}
