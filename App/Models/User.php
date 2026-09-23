<?php
declare(strict_types=1);

namespace App\Models;

use Database\ORM\Model;
use Database\ORM\Relation;

class User extends Model
{
    protected static string $table = 'users';

    public function role(): Relation
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function store(): Relation
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function posts(): Relation
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
