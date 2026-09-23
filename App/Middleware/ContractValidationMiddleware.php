<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\JsonSchemaValidator;
use App\Core\Logger;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * JSON Schema contract validation middleware.
 *
 * Validates the response body against a JSON Schema definition attached
 * to the route metadata. Skipped in production unless RESPONSE_SCHEMA_VALIDATION=true.
 *
 * Usage in routes:
 *   $router->getApi('v1', '/users/{id}', [UserController::class, 'show'], [], [
 *       'response_schema' => require __DIR__ . '/../schemas/user.json',
 *   ]);
 *
 * Response schema file example (schemas/user.json):
 *   return [
 *       'type' => 'object',
 *       'properties' => [
 *           'id'    => ['type' => 'integer'],
 *           'name'  => ['type' => 'string', 'minLength' => 1],
 *           'email' => ['type' => 'string', 'format' => 'email'],
 *       ],
 *       'required' => ['id', 'name'],
 *   ];
 */
class ContractValidationMiddleware implements MiddlewareInterface
{
    private Logger $logger;
    private bool $enabled;

    public function __construct(
        ?Logger $logger = null,
        bool|null $enabled = null
    ) {
        $this->logger  = $logger ?? new Logger(dirname(__DIR__, 2) . '/storage/logs/contract_validation.log');
        $this->enabled = $enabled ?? ($_ENV['RESPONSE_SCHEMA_VALIDATION'] ?? 'false') === 'true';
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        // Skip if validation is disabled or in production
        if (!$this->enabled) {
            return $next($request, $response);
        }

        $response = $next($request, $response);

        // Only validate successful JSON responses
        $content = $response->getContent();
        if (empty($content) || $response->getStatusCode() >= 400) {
            return $response;
        }

        // Try to parse as JSON
        $data = json_decode($content, true);
        if ($data === null) {
            return $response;
        }

        // Get schema from request attribute (set by router)
        $schema = $request->getCustomAttribute('response_schema');
        if (!$schema || !is_array($schema)) {
            return $response;
        }

        $errors = JsonSchemaValidator::validate($data, $schema);
        if (!empty($errors)) {
            $this->logger->error(
                "Contract violation on {$request->getMethod()} {$request->getPath()}: "
                . implode('; ', $errors)
            );

            // In development, fail the request; in other envs, log and continue
            if ($_ENV['APP_ENV'] === 'development' || $_ENV['APP_DEBUG'] === 'true') {
                $response->setStatusCode(500);
                $response->setHeader('Content-Type', 'application/json');
                $response->setContent(json_encode([
                    'success' => false,
                    'error'   => 'CONTRACT_VIOLATION',
                    'message' => 'Response does not match expected schema',
                    'errors'  => $errors,
                ]));
            }
        }

        return $response;
    }
}
