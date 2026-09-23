<?php
declare(strict_types=1);

namespace App\GraphQL;

use App\Core\Request;
use App\Core\Response;

/**
 * Lightweight GraphQL engine — no external dependencies.
 *
 * Supports:
 *   - Queries with nested field selection
 *   - Arguments with defaults and required checks
 *   - Mutations (same execution model as queries)
 *   - Scalar resolution (string, int, float, boolean)
 *   - Object resolution via callable resolvers
 *
 * Usage:
 *   $schema = new Schema('Query');
 *   $schema->addType(new Type('Query')
 *       ->field('user', new Field('User', fn($args) => UserRepository::find($args['id'])))
 *       ->field('users', new Field('[User]', fn() => UserRepository::all()))
 *   );
 *   $schema->addType(new Type('User')
 *       ->field('id', new Field('ID', fn($obj) => $obj['id']))
 *       ->field('name', new Field('String', fn($obj) => $obj['name']))
 *   );
 *
 *   $engine = new Engine($schema);
 *   $result = $engine->execute($request);
 */
class Engine
{
    public function __construct(
        public readonly Schema $schema,
    ) {}

    /**
     * Execute a GraphQL request and return the result as a Response.
     */
    public function execute(Request $request, Response $response): Response
    {
        $body = $request->getBodyParams();
        $query = $body['query'] ?? '';
        $variables = $body['variables'] ?? [];
        $operationName = $body['operationName'] ?? null;

        if (empty($query)) {
            return $this->error($response, 'Query is required', 400);
        }

        try {
            $ast = $this->parse($query);
            $operation = $this->findOperation($ast, $operationName);

            if (!$operation) {
                return $this->error($response, 'No operation found', 400);
            }

            $rootType = ($operation['type'] ?? 'query') === 'mutation'
                ? ($this->schema->mutationType ?? 'Mutation')
                : $this->schema->queryType;

            $root = $this->resolveFields($rootType, $operation['selectionSet'] ?? [], $variables, []);

            return $response->jsonResponse([
                'success' => true,
                'data'    => $root,
            ]);
        } catch (\Throwable $e) {
            return $this->error($response, $e->getMessage(), 400);
        }
    }

    /**
     * Resolve a selection set into an associative array.
     *
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $parent  Parent object for field resolution
     */
    protected function resolveFields(string $typeName, array $selections, array $variables, array $parent): mixed
    {
        $type = $this->schema->getType($typeName);
        if (!$type) {
            return null;
        }

        if (empty($selections)) {
            return $parent;
        }

        // If parent is a scalar, return it directly
        if (is_scalar($parent) || $parent === null) {
            return $parent;
        }

        // If parent is a list, resolve each item
        if (is_array($parent) && !$this->isArrayLike($parent)) {
            // Associative array — treat as object
        } elseif (is_array($parent) && $this->isArrayLike($parent)) {
            $results = [];
            foreach ($parent as $item) {
                $results[] = $this->resolveFields($typeName, $selections, $variables, is_array($item) ? $item : []);
            }
            return $results;
        }

        // Object resolution
        $result = [];
        foreach ($selections as $selection) {
            $fieldName = $selection['name'];

            if (!$type->hasField($fieldName)) {
                continue;
            }

            $field = $type->getField($fieldName);
            $args  = $this->resolveArgs($field, $selection['args'] ?? [], $variables);

            $resolver = $field->resolve ?? function ($args, $parent, string $fieldName) {
                // Default: resolve scalar fields from parent by field name
                if (is_array($parent) && array_key_exists($fieldName, $parent)) {
                    return $parent[$fieldName];
                }
                return $parent[$args['id'] ?? 'id'] ?? null;
            };

            $value = $resolver($args, $parent, $fieldName);

            // Recursively resolve nested selections
            if (isset($selection['selectionSet']) && !is_scalar($value) && $value !== null) {
                $childType = $this->getTypeNameFromField($field->type);
                if ($this->schema->hasType($childType)) {
                    $value = $this->resolveFields($childType, $selection['selectionSet'], $variables, is_array($value) ? $value : []);
                }
            }

            $result[$fieldName] = $value;
        }

        return $result;
    }

    /**
     * Resolve field arguments with defaults and required checks.
     */
    protected function resolveArgs(Field $field, array $providedArgs, array $variables): array
    {
        $args = [];
        foreach ($field->args as $argDef) {
            $name = $argDef->name;
            $value = $providedArgs[$name] ?? $argDef->default;

            // Apply variable substitution
            if ($value === null && isset($variables[$name])) {
                $value = $variables[$name];
            }

            if ($argDef->required && $value === null) {
                throw new \InvalidArgumentException("Missing required argument: {$name}");
            }

            $args[$name] = $value;
        }
        return $args;
    }

    /**
     * Parse a GraphQL query string into an AST.
     */
    protected function parse(string $query): array
    {
        $query = $this->cleanQuery($query);
        return $this->parseDocument($query);
    }

    protected function cleanQuery(string $query): string
    {
        // Remove comments
        $query = preg_replace('/#[^\n]*/', '', $query);
        // Normalize whitespace
        $query = preg_replace('/\s+/', ' ', $query);
        return trim($query);
    }

    /**
     * Parse a full document (may contain multiple operations).
     */
    protected function parseDocument(string $query): array
    {
        $tokens = $this->tokenize($query);
        $pos    = 0;
        return $this->parseDefinitionList($tokens, $pos);
    }

    /**
     * Parse a list of top-level definitions.
     */
    protected function parseDefinitionList(array $tokens, int &$pos): array
    {
        $definitions = [];
        while ($pos < count($tokens)) {
            if ($tokens[$pos] === '}') {
                break;
            }
            $definitions[] = $this->parseDefinition($tokens, $pos);
        }
        return $definitions;
    }

    /**
     * Parse a single top-level definition (operation or fragment).
     */
    protected function parseDefinition(array $tokens, int &$pos): array
    {
        // Skip non-alphanumeric tokens
        while ($pos < count($tokens) && !ctype_alnum($tokens[$pos][0] ?? '') && $tokens[$pos] !== '{' && $tokens[$pos] !== '}' && $tokens[$pos] !== '(' && $tokens[$pos] !== ')') {
            $pos++;
        }

        if ($pos >= count($tokens)) {
            return [];
        }

        $token = $tokens[$pos];

        // Operation definition
        if (in_array($token, ['query', 'mutation', 'subscription'])) {
            return $this->parseOperation($tokens, $pos);
        }

        // Fragment definition
        if ($token === 'fragment') {
            return $this->parseFragment($tokens, $pos);
        }

        // Inline field (should not happen at top level)
        $pos++;
        return [];
    }

    /**
     * Parse an operation definition.
     */
    protected function parseOperation(array $tokens, int &$pos): array
    {
        $opType = $tokens[$pos++]; // query / mutation
        $name   = null;
        $vars   = [];
        $selectionSet = [];

        // Optional name
        if ($pos < count($tokens) && ctype_alnum($tokens[$pos][0] ?? '')) {
            $name = $tokens[$pos++];
        }

        // Optional variables
        if ($pos < count($tokens) && $tokens[$pos] === '(') {
            $vars = $this->parseVariableDefinitions($tokens, $pos);
        }

        // Selection set
        if ($pos < count($tokens) && $tokens[$pos] === '{') {
            $selectionSet = $this->parseSelectionSet($tokens, $pos);
        }

        return [
            'type'          => $opType,
            'name'          => $name,
            'variables'     => $vars,
            'selectionSet'  => $selectionSet,
        ];
    }

    /**
     * Parse variable definitions like ($id: ID!, $limit: Int = 10).
     */
    protected function parseVariableDefinitions(array $tokens, int &$pos): array
    {
        $vars = [];
        $pos++; // skip '('
        while ($pos < count($tokens) && $tokens[$pos] !== ')') {
            // Skip '$'
            if ($tokens[$pos] === '$') $pos++;
            $varName = $tokens[$pos++];
            $pos++; // skip ':'
            $varType = $tokens[$pos++];
            $default = null;
            if ($tokens[$pos] === '=') {
                $pos++;
                $default = $this->parseValue($tokens, $pos);
            }
            // Skip '!'
            if ($tokens[$pos] === '!') $pos++;
            $vars[] = ['name' => $varName, 'type' => $varType, 'default' => $default];
            // Skip comma
            if (isset($tokens[$pos]) && $tokens[$pos] === ',') $pos++;
        }
        if ($pos < count($tokens)) $pos++; // skip ')'
        return $vars;
    }

    /**
     * Parse a selection set { field1 field2 { nested } }.
     */
    protected function parseSelectionSet(array $tokens, int &$pos): array
    {
        $selections = [];
        $pos++; // skip '{'
        while ($pos < count($tokens) && $tokens[$pos] !== '}') {
            $selections[] = $this->parseSelection($tokens, $pos);
        }
        if ($pos < count($tokens)) $pos++; // skip '}'
        return $selections;
    }

    /**
     * Parse a single selection (field with optional alias, args, and sub-selection).
     */
    protected function parseSelection(array $tokens, int &$pos): array
    {
        $selection = ['name' => null, 'alias' => null, 'args' => [], 'selectionSet' => null];

        // Optional alias
        if ($pos < count($tokens) && ctype_alnum($tokens[$pos][0] ?? '') && isset($tokens[$pos + 1]) && $tokens[$pos + 1] === ':') {
            $selection['alias'] = $tokens[$pos++];
            $pos++; // skip ':'
        }

        // Field name
        if ($pos < count($tokens) && ctype_alnum($tokens[$pos][0] ?? '')) {
            $selection['name'] = $tokens[$pos++];
        }

        // Optional arguments
        if ($pos < count($tokens) && $tokens[$pos] === '(') {
            $selection['args'] = $this->parseArgumentList($tokens, $pos);
        }

        // Optional nested selection set
        if ($pos < count($tokens) && $tokens[$pos] === '{') {
            $selection['selectionSet'] = $this->parseSelectionSet($tokens, $pos);
        }

        return $selection;
    }

    /**
     * Parse argument list (arg1: value1, arg2: value2).
     */
    protected function parseArgumentList(array $tokens, int &$pos): array
    {
        $args = [];
        $pos++; // skip '('
        while ($pos < count($tokens) && $tokens[$pos] !== ')') {
            $name = $tokens[$pos++];
            $pos++; // skip ':'
            $value = $this->parseValue($tokens, $pos);
            $args[$name] = $value;
            if (isset($tokens[$pos]) && $tokens[$pos] === ',') $pos++;
        }
        if ($pos < count($tokens)) $pos++; // skip ')'
        return $args;
    }

    /**
     * Parse a literal value (string, int, float, boolean, null, enum, list, object).
     */
    protected function parseValue(array $tokens, int &$pos): mixed
    {
        if ($pos >= count($tokens)) return null;

        $token = $tokens[$pos];

        // String
        if (str_starts_with($token, '"') && str_ends_with($token, '"')) {
            $pos++;
            return substr($token, 1, -1);
        }

        // Variable reference
        if (str_starts_with($token, '$')) {
            $pos++;
            return ['__var' => substr($token, 1)];
        }

        // Integer
        if (ctype_digit($token) || ($token[0] === '-' && ctype_digit(substr($token, 1)))) {
            $pos++;
            return (int)$token;
        }

        // Float
        if (is_numeric($token) && str_contains($token, '.')) {
            $pos++;
            return (float)$token;
        }

        // Boolean / null
        if (in_array($token, ['true', 'false', 'null'])) {
            $pos++;
            return $token === 'true' ? true : ($token === 'false' ? false : null);
        }

        // List [a, b, c]
        if ($token === '[') {
            $pos++;
            $list = [];
            while ($pos < count($tokens) && $tokens[$pos] !== ']') {
                $list[] = $this->parseValue($tokens, $pos);
                if (isset($tokens[$pos]) && $tokens[$pos] === ',') $pos++;
            }
            if ($pos < count($tokens)) $pos++;
            return $list;
        }

        // Object { a: 1, b: 2 }
        if ($token === '{') {
            $pos++;
            $obj = [];
            while ($pos < count($tokens) && $tokens[$pos] !== '}') {
                $key = $tokens[$pos++];
                $pos++; // skip ':'
                $val = $this->parseValue($tokens, $pos);
                $obj[$key] = $val;
                if (isset($tokens[$pos]) && $tokens[$pos] === ',') $pos++;
            }
            if ($pos < count($tokens)) $pos++;
            return $obj;
        }

        // Enum value (unquoted identifier)
        if (ctype_alnum($token[0] ?? '')) {
            $pos++;
            return $token;
        }

        $pos++;
        return null;
    }

    /**
     * Tokenize a GraphQL query string.
     */
    protected function tokenize(string $query): array
    {
        // Remove string literals and replace with placeholders
        $tokens = [];
        $len = strlen($query);
        $i = 0;

        while ($i < $len) {
            $ch = $query[$i];

            // Skip whitespace
            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            // String literal
            if ($ch === '"') {
                $start = ++$i;
                while ($i < $len && $query[$i] !== '"') {
                    if ($query[$i] === '\\') $i++;
                    $i++;
                }
                $tokens[] = substr($query, $start - 1, $i - $start + 1);
                $i++;
                continue;
            }

            // Punctuation and operators
            if (str_contains('{}():!,=[]', $ch)) {
                $tokens[] = $ch;
                $i++;
                continue;
            }

            // Identifier or number
            $start = $i;
            while ($i < $len && !ctype_space($query[$i]) && !str_contains('{}():!,=[]"', $query[$i])) {
                $i++;
            }
            $tokens[] = substr($query, $start, $i - $start);
        }

        return $tokens;
    }

    /**
     * Find the operation to execute (by name or first query/mutation).
     */
    protected function findOperation(array $definitions, ?string $operationName = null): ?array
    {
        foreach ($definitions as $def) {
            if (!isset($def['type'])) continue;

            if ($operationName !== null && ($def['name'] ?? '') === $operationName) {
                return $def;
            }
        }

        // Default: return first query or mutation
        foreach ($definitions as $def) {
            if (($def['type'] ?? '') === 'query' || ($def['type'] ?? '') === 'mutation') {
                return $def;
            }
        }

        return null;
    }

    /**
     * Extract the base type name from a GraphQL type string (handles [User] and User!).
     */
    protected function getTypeNameFromField(string $typeStr): string
    {
        // Strip non-alpha characters from start/end
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $typeStr);
        return $clean ?: $typeStr;
    }

    /**
     * Check if an array is list-like (sequential integer keys starting from 0).
     * Empty arrays are NOT considered list-like (they are treated as objects).
     */
    protected function isArrayLike(array $arr): bool
    {
        if (empty($arr)) return false;
        $keys = array_keys($arr);
        return $keys === range(0, count($keys) - 1);
    }

    protected function error(Response $response, string $message, int $code = 400): Response
    {
        $response->setStatusCode($code);
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode([
            'success' => false,
            'errors'  => [['message' => $message]],
        ]));
        return $response;
    }
}
