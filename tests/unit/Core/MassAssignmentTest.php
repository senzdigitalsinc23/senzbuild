<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Database\ORM\Model;

class MassAssignmentTest extends TestCase
{
    public function test_guarded_attributes_are_skipped(): void
    {
        $model = new TestFillableModel(['name' => 'John', 'role' => 'admin']);
        $this->assertSame('John', $model->name);
        $this->assertNull($model->role); // guarded by default
    }

    public function test_fillable_list_restricts_assignment(): void
    {
        $model = new TestFillableModel(['name' => 'John', 'email' => 'john@test.com']);
        $this->assertSame('John', $model->name);
        $this->assertSame('john@test.com', $model->email);
    }

    public function test_guarded_list_works(): void
    {
        $model = new TestGuardedModel(['name' => 'John', 'role' => 'admin']);
        $this->assertSame('John', $model->name);
        $this->assertNull($model->role);
    }

    public function test_create_safe_applies_protection(): void
    {
        $model = TestFillableModel::createSafe(['name' => 'Test', 'role' => 'hacker']);
        $this->assertSame('Test', $model->name);
        $this->assertNull($model->role);
    }

    public function test_is_fillable_returns_true_for_allowed_keys(): void
    {
        $model = new TestFillableModel();
        $this->assertTrue($model->isFillable('name'));
        $this->assertFalse($model->isFillable('role'));
    }

    public function test_get_fillable_returns_list(): void
    {
        $model = new TestFillableModel();
        $this->assertSame(['name', 'email'], $model->getFillable());
    }

    public function test_get_guarded_returns_list(): void
    {
        $model = new TestFillableModel();
        $this->assertSame(['role'], $model->getGuarded());
    }
}

class TestFillableModel extends Model
{
    protected static string $table = 'test_fillable';
    protected array $fillable = ['name', 'email'];
    protected array $guarded = ['role'];
}

class TestGuardedModel extends Model
{
    protected static string $table = 'test_guarded';
    protected array $guarded = ['role'];
}
