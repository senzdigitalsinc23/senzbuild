<?php
declare(strict_types=1);

namespace App\Models;

use Database\ORM\Model;

class Student extends Model
{
    protected static string $table = 'students';
    protected array $fillable = ['student_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'class_id', 'parent_contact', 'status'];
}
