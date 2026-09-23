<?php
declare(strict_types=1);
// app/Middleware/RequireScopesMiddleware.php
namespace App\Middleware;

use App\Support\ApiPath;

class RequireScopesMiddleware
{
    public function __construct(private array $required) {}

    public function handle(): void
    {
        if (!ApiPath::isApi() || ApiPath::isWhitelisted()) return;

        $scopes = $_SERVER['API_KEY_SCOPES'] ?? [];
        foreach ($this->required as $need) {
            if (!in_array($need, $scopes, true)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Missing required scope: ' . $need]);
                exit;
            }
        }
    }
}
