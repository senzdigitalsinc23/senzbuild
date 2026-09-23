<?php
declare(strict_types=1);

namespace Database\ORM;

class Relation
{
    public function __construct(
        public string $relatedModel,
        public string $foreignKey,
        public string $localKey,
        public string $type, // 'hasOne', 'hasMany', 'belongsTo', 'belongsToMany'
        public ?string $pivotTable = null,
        public ?string $relatedKey = null,
        public ?\Closure $callback = null
    ) {}
}
