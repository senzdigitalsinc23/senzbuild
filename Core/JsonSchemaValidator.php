<?php
declare(strict_types=1);

namespace App\Core;

/**
 * JSON Schema response validator middleware.
 *
 * Validates API responses against a JSON Schema definition before sending
 * them to the client. Catches contract violations during development and
 * can be enabled in production with a warning log.
 *
 * Schema format (inline):
 *   [
 *     'type' => 'object',
 *     'properties' => [
 *       'id'      => ['type' => 'integer'],
 *       'name'    => ['type' => 'string'],
 *       'email'   => ['type' => 'string', 'format' => 'email'],
 *       'tags'    => ['type' => 'array', 'items' => ['type' => 'string']],
 *     ],
 *     'required' => ['id', 'name'],
 *   ]
 *
 * Usage:
 *   $router->getApi('v1', '/users/{id}', [UserController::class, 'show'], [], [
 *       'response_schema' => require __DIR__ . '/../schemas/user.json',
 *   ]);
 */
class JsonSchemaValidator
{
    /**
     * Validate $data against $schema.
     *
     * @return array<string> List of validation error messages (empty if valid)
     */
    public static function validate(mixed $data, array $schema): array
    {
        $errors = [];
        self::validateNode($data, $schema, '', $errors);
        return $errors;
    }

    private static function validateNode(mixed $data, array $schema, string $path, array &$errors): void
    {
        // Type check
        if (isset($schema['type'])) {
            $typeMatch = self::checkType($data, $schema['type']);
            if (!$typeMatch) {
                $errors[] = "{$path}: expected type '{$schema['type']}', got " . self::jsonType($data);
                return;
            }
        }

        // Enum check
        if (isset($schema['enum']) && !in_array($data, $schema['enum'], true)) {
            $errors[] = "{$path}: value must be one of [" . implode(', ', array_map(fn($v) => json_encode($v), $schema['enum'])) . "]";
        }

        // String constraints
        if (is_string($data)) {
            if (isset($schema['minLength']) && strlen($data) < $schema['minLength']) {
                $errors[] = "{$path}: string length must be >= {$schema['minLength']}";
            }
            if (isset($schema['maxLength']) && strlen($data) > $schema['maxLength']) {
                $errors[] = "{$path}: string length must be <= {$schema['maxLength']}";
            }
            if (isset($schema['pattern']) && !preg_match($schema['pattern'], $data)) {
                $errors[] = "{$path}: string does not match pattern '{$schema['pattern']}'";
            }
            if (isset($schema['format'])) {
                self::validateFormat($data, $schema['format'], $path, $errors);
            }
        }

        // Number constraints
        if (is_numeric($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: value must be >= {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: value must be <= {$schema['maximum']}";
            }
        }

        // Object constraints
        if (is_array($data) && !self::isArrayLike($data)) {
            // Required fields
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $req) {
                    if (!array_key_exists($req, $data)) {
                        $errors[] = "{$path}: missing required field '{$req}'";
                    }
                }
            }
            // Properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $prop => $propSchema) {
                    if (array_key_exists($prop, $data)) {
                        self::validateNode($data[$prop], $propSchema, "{$path}.{$prop}", $errors);
                    }
                }
            }
            // Additional properties
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $allowed = array_keys($schema['properties'] ?? []);
                foreach (array_keys($data) as $key) {
                    if (!in_array($key, $allowed, true)) {
                        $errors[] = "{$path}: unexpected field '{$key}'";
                    }
                }
            }
        }

        // Array constraints
        if (is_array($data) && self::isArrayLike($data)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "{$path}: array must have >= {$schema['minItems']} items";
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = "{$path}: array must have <= {$schema['maxItems']} items";
            }
            if (isset($schema['items'])) {
                foreach ($data as $i => $item) {
                    self::validateNode($item, $schema['items'], "{$path}[{$i}]", $errors);
                }
            }
        }
    }

    private static function checkType(mixed $data, string $type): bool
    {
        return match ($type) {
            'string'   => is_string($data),
            'integer'  => is_int($data),
            'number'   => is_numeric($data),
            'boolean'  => is_bool($data),
            'array'    => is_array($data),
            'object'   => is_array($data) && !self::isArrayLike($data),
            'null'     => $data === null,
            default    => true,
        };
    }

    private static function jsonType(mixed $data): string
    {
        if ($data === null) return 'null';
        if (is_bool($data)) return 'boolean';
        if (is_int($data)) return 'integer';
        if (is_float($data)) return 'number';
        if (is_string($data)) return 'string';
        if (is_array($data)) return self::isArrayLike($data) ? 'array' : 'object';
        return gettype($data);
    }

    private static function validateFormat(string $data, string $format, string $path, array &$errors): void
    {
        match ($format) {
            'email' => filter_var($data, FILTER_VALIDATE_EMAIL) !== false
                or $errors[] = "{$path}: must be a valid email address",
            'uri'   => filter_var($data, FILTER_VALIDATE_URL) !== false
                or $errors[] = "{$path}: must be a valid URI",
            'date'  => strtotime($data) !== false
                or $errors[] = "{$path}: must be a valid date",
            'uuid'  => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $data)
                or $errors[] = "{$path}: must be a valid UUID",
            default => null,
        };
    }

    private static function isArrayLike(array $arr): bool
    {
        if (empty($arr)) return true;
        $keys = array_keys($arr);
        return $keys === range(0, count($keys) - 1);
    }
}
