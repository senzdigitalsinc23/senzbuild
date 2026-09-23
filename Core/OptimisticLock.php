<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Optimistic Locking — prevents concurrent modification conflicts.
 *
 * Add `use OptimisticLock` trait to any model that needs version protection.
 * The model must have an `version` integer column.
 *
 * Usage:
 *   class Product extends Model {
 *       use OptimisticLock;
 *       protected static string $lockColumn = 'version';
 *   }
 *
 *   $product = Product::find(1);
 *   $product->price = 99;
 *   $product->save();  // increments version, checks for conflicts
 */
trait OptimisticLock
{
    protected static string $lockColumn = 'version';

    /**
     * Override save() to add optimistic locking.
     */
    public function save(): bool
    {
        $id = $this->getId();

        if ($id) {
            // Update with version check
            $currentVersion = $this->attributes[self::$lockColumn] ?? null;
            if ($currentVersion !== null) {
                $db = Database::getInstance()->getConnection();
                $table = static::$table;
                $sql = "UPDATE {$table} SET "
                     . implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($this->attributes)))
                     . ", `" . static::$lockColumn . "` = `" . static::$lockColumn . "` + 1"
                     . " WHERE id = :id AND `" . static::$lockColumn . "` = :version";

                $params = array_merge($this->attributes, [
                    'id'       => $id,
                    'version'  => $currentVersion,
                ]);
                $stmt = $db->prepare($sql);
                $affected = $stmt->execute($params);

                if (!$affected || $stmt->rowCount() === 0) {
                    throw new OptimisticLockException(
                        "Record was modified by another user. Please refresh and try again."
                    );
                }

                // Increment local version
                $this->attributes[self::$lockColumn] = $currentVersion + 1;
                return true;
            }
        }

        // Fall back to regular save for new records or models without lock column
        return parent::save();
    }

    /**
     * Get the current lock version.
     */
    public function getLockVersion(): ?int
    {
        return $this->attributes[self::$lockColumn] ?? null;
    }
}

/**
 * Exception thrown when an optimistic lock conflict is detected.
 */
class OptimisticLockException extends \Exception
{
}
