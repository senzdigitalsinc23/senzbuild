<?php
declare(strict_types=1);

namespace Database\ORM\Traits;

use App\Utils\AuditLogger;
use App\Core\Session;

trait Auditable
{
    protected array $originalAttributes = [];

    /**
     * Sync original attributes to track changes.
     * Should be called after loading the model from DB.
     */
    public function syncOriginal(): void
    {
        $this->originalAttributes = $this->attributes;
    }

    /**
     * Log changes to the audit trail before saving.
     */
    protected function auditChanges(): void
    {
        if (empty($this->originalAttributes)) {
            return;
        }

        $changes = [];
        foreach ($this->attributes as $key => $value) {
            $oldValue = $this->originalAttributes[$key] ?? null;
            if ($value !== $oldValue) {
                $changes[$key] = [$oldValue, $value];
            }
        }

        if (!empty($changes)) {
            AuditLogger::logChange(
                get_class($this),
                (string)$this->getId(),
                'update',
                Session::get('user')['id'] ?? 'system',
                $changes
            );
        }
    }
}
