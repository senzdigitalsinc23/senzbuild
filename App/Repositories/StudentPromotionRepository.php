<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

class StudentPromotionRepository
{
    private PDO $db;
    private const TABLE = 'students';
    private const PROMOTION_TABLE = 'student_promotions';
    private const CLASS_TABLE = 'class_enrollments';
    private const NEXT_CLASS_TABLE = 'class_progress';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? new PDO(
            'sqlite:' . dirname(__DIR__, 2) . '/storage/app.db',
            null,
            null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Resolve student numbers to IDs in batch.
     *
     * @param string[] $studentNos
     * @return array<string, int>
     */
    public function resolveStudentNosBatch(array $studentNos): array
    {
        if (empty($studentNos)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentNos), '?'));
        $sql = "SELECT student_no, id FROM " . static::TABLE . " WHERE student_no IN ($placeholders)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($studentNos);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $result[(string)$row['student_no']] = (int)$row['id'];
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Check which students have been promoted.
     *
     * @param int[] $studentIds
     * @param int $classId
     * @return array<int, bool>
     */
    public function hasBeenPromotedBatch(array $studentIds, int $classId): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT student_id FROM " . static::PROMOTION_TABLE . " WHERE student_id IN ($placeholders) AND class_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($studentIds, [$classId]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($studentIds as $id) {
                $result[$id] = false;
            }
            foreach ($rows as $row) {
                $result[(int)$row['student_id']] = true;
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get current class for each student in batch.
     *
     * @param int[] $studentIds
     * @param int $academicYearId
     * @return array<int, int|null>
     */
    public function getStudentCurrentClassesBatch(array $studentIds, int $academicYearId): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT student_id, class_id FROM " . static::CLASS_TABLE . " WHERE student_id IN ($placeholders) AND academic_year_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($studentIds, [$academicYearId]));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($studentIds as $id) {
                $result[$id] = null;
            }
            foreach ($rows as $row) {
                $result[(int)$row['student_id']] = (int)$row['class_id'];
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get next class for each student in batch.
     *
     * @param int[] $studentIds
     * @return array<int, int|null>
     */
    public function getNextClassesBatch(array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sql = "SELECT id, next_class_id FROM " . static::NEXT_CLASS_TABLE . " WHERE id IN ($placeholders)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($studentIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($studentIds as $id) {
                $result[$id] = null;
            }
            foreach ($rows as $row) {
                $result[(int)$row['id']] = $row['next_class_id'] ? (int)$row['next_class_id'] : null;
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }
}
