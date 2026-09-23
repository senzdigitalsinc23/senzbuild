<?php
declare(strict_types=1);

namespace App\Core;

use Database\ORM\Model;

class Validator
{
    protected $data;
    protected $rules;
    protected $errors = [];
    protected $db;
    protected $customMessages = [];

    public function __construct($data, $rules, $db = null, $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->db = $db; // PDO instance
        $this->customMessages = $customMessages;
        $this->validate();
    }

    protected function validate()
    {
        foreach ($this->rules as $field => $rules) {
            $rulesArr = explode('|', $rules);
            $value = $this->data[$field] ?? null;

            $bail = in_array('bail', $rulesArr); // check if bail exists

            foreach ($rulesArr as $rule) {
                if ($rule === 'bail') continue; // skip bail itself

                $params = null;
                if (strpos($rule, ':') !== false) {
                    [$rule, $params] = explode(':', $rule, 2);
                }

                $method = "validate" . ucfirst($rule);

                if (method_exists($this, $method)) {
                    $this->$method($field, $value, $params);

                    // if bail is enabled and this field already has an error → stop
                    if ($bail && isset($this->errors[$field])) {
                        break;
                    }
                }
            }
        }
    }


    /** ---------------- Core Validators ---------------- **/

    protected function addError($field, $rule, $defaultMessage)
    {
        $key = $field . '.' . strtolower($rule);

        if (isset($this->customMessages[$key])) {
            $this->errors[$field][] = $this->customMessages[$key];
        } else {
            $this->errors[$field][] = $defaultMessage;
        }
    }

    protected function validateRequired($field, $value)
    {
        if (is_null($value) || $value === '') {
            $this->addError($field, 'required', "$field is required.");
        }
    }

    protected function validateString($field, $value)
    {
        if (!is_null($value)) {
            if (!is_string($value)) {
                $this->addError($field, 'string', "$field must be a string.");
            } else {
                // Auto-trim and strip_tags for string fields
                $sanitized = trim(strip_tags($value));
                $this->data[$field] = $sanitized;
            }
        }
    }

    protected function validateInteger($field, $value)
    {
        if (!is_null($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, 'integer', "$field must be an integer.");
        }
    }

    protected function validateEmail($field, $value)
    {
        if ($value != '' && !is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', "$field must be a valid email.");
        }
    }

    protected function validateMin($field, $value, $param)
    {
        if (!is_null($value) && strlen($value) < (int)$param) {
            $this->addError($field, 'min', "$field must be at least $param characters.");
        }
    }

    protected function validateMax($field, $value, $param)
    {
        if (!is_null($value) && strlen($value) > (int)$param) {
            $this->addError($field, 'max', "$field must not exceed $param characters.");
        }
    }

    protected function validateIn($field, $value, $param)
    {
        $options = explode(',', $param);
        if (!is_null($value) && !in_array($value, $options)) {
            $this->addError($field, 'in', "$field must be one of: " . implode(', ', $options));
        }
    }

    protected function validateRegex($field, $value, $pattern)
    {
        if ($value != '' && !is_null($value) && !preg_match($pattern, $value)) {
            $this->addError($field, 'regex', "$field has invalid format.");
        }
    }

    /** ---------------- Date Validators ---------------- **/

    protected function validateDate($field, $value)
    {
        if (!is_null($value) && !strtotime($value)) {
            $this->addError($field, 'date', "$field must be a valid date.");
        }
    }

    protected function validateBefore($field, $value, $param)
    {
        if (!$value) return;

        $date = strtotime($value);
        $limit = strtotime($param);

        if ($date >= $limit) {
            $this->addError($field, 'before', "$field must be before " . date("Y-m-d", $limit));
        }
    }

    protected function validateAfter($field, $value, $param)
    {
        if (!$value) return;

        $date = strtotime($value);
        $limit = strtotime($param);

        if ($date <= $limit) {
            $this->addError($field, 'after', "$field must be after " . date("Y-m-d", $limit));
        }
    }

    /** ---------------- Database Validators ---------------- **/

    protected function validateUnique($field, $value, $params)
    {

        if (!$this->db) {
            echo json_encode(['success' => true, 'message' => 'Database connection not exist for operation']);exit;
            return;
        }

        // params = table,column[,additional_column]
        $parts = explode(',', $params);
        $table = $parts[0] ?? null;
        $column = $parts[1] ?? null;

        if (!$table || !$column) {
            echo json_encode(['success' => true, 'message' => 'Please specify table and column to compare']);exit;
            return;
        }

        // If multiple columns are passed (e.g., email,phone), check them all
        $columns = array_slice($parts, 1);

        foreach ($columns as $col) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$col} = :value AND `$col` <> '' LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['value' => $value]);

            if ($stmt->fetchColumn() > 0) {
                $this->addError($field, 'unique', ucfirst($col) . " already exists.");
                return;
            }
        }
    }

    protected function validateExists($field, $value, $param)
    {
        if ($this->db && $value) {
            [$table, $col] = explode(',', $param);

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} = :val");
            $stmt->execute([':val' => $value]);

            if ($stmt->fetchColumn() == 0) {
                $this->addError($field, 'exists', "$field must exist in $table.");
            }
        }
    }

 
    /** ---------------- Helpers ---------------- **/

    public function fails()
    {
        return !empty($this->errors);
    }

    public function errors()
    {
        return $this->errors;
    }
    
    public function setErrors($field,  $message) {
        $this->addError($field, 'duplicate', "$field $message.");
    }
}
