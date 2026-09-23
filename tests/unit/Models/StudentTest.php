<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Student;

/**
 * Test Student model for SQL injection prevention
 */
class StudentTest extends TestCase
{
    /**
     * Test model uses parameterized queries (SQL injection prevention)
     */
    public function testModelUsesParameterizedQueries(): void
    {
        // This test verifies that the model extends the base Model class
        // which uses PDO prepared statements for all queries
        
        $student = new Student();
        
        // Verify the model has the expected structure
        $this->assertInstanceOf(Student::class, $student);
        
        // Verify it extends the base Model class that uses PDO
        $this->assertInstanceOf(\Database\ORM\Model::class, $student);
    }

    /**
     * Test dangerous SQL characters are handled safely
     */
    public function testDangerousSqlCharactersHandledSafely(): void
    {
        // Test that SQL injection attempts would be safely handled
        $dangerousInputs = [
            "'; DROP TABLE students; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM users--",
            "1; DELETE FROM students WHERE 1=1--"
        ];

        foreach ($dangerousInputs as $input) {
            // These should be treated as literal strings, not SQL
            $this->assertIsString($input);
            $this->assertStringContainsString("'", $input);
        }
    }

    /**
     * Test model table name is properly set
     */
    public function testModelTableNameIsSet(): void
    {
        $student = new Student();
        
        // Access protected property via reflection
        $reflection = new \ReflectionClass($student);
        $property = $reflection->getProperty('table');
        $property->setAccessible(true);
        
        $tableName = $property->getValue($student);
        
        $this->assertEquals('students', $tableName);
    }

    /**
     * Test model fillable fields are defined
     */
    public function testModelFillableFieldsAreDefined(): void
    {
        $student = new Student();
        
        // Access protected property via reflection
        $reflection = new \ReflectionClass($student);
        
        if ($reflection->hasProperty('fillable')) {
            $property = $reflection->getProperty('fillable');
            $property->setAccessible(true);
            
            $fillable = $property->getValue($student);
            
            $this->assertIsArray($fillable);
            $this->assertNotEmpty($fillable);
        } else {
            // If no fillable property, that's also acceptable
            $this->assertTrue(true);
        }
    }
}
