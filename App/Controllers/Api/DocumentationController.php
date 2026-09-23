<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Config;
use App\Core\View;
use App\Core\Response;
use App\Services\SwaggerGenerator;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: "API Project Specification",
    version: "1.0.0",
    description: "A comprehensive API for managing business operations."
)]
#[OA\Server(
    url: "http://localhost:8000/api/v1",
    description: "Development server"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-API-Key",
    description: "API Key for authentication"
)]
class DocumentationController extends Controller
{
    protected View $view;
    protected SwaggerGenerator $swagger;

    public function __construct(View $view, SwaggerGenerator $swagger) {
        $this->view = $view;
        $this->swagger = $swagger;
    }

    /**
     * Returns the Swagger OpenAPI JSON.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(\App\Core\Request $request, Response $response): Response
    {
        try {
            $cachePath = Config::get('swagger.cache_path', dirname(__DIR__, 3) . '/storage/cache/openapi.json');

            // Serve cached version if available and not in debug mode
            $debug = env('APP_DEBUG', false);
            if (!$debug && file_exists($cachePath) && (time() - filemtime($cachePath)) < 3600) {
                $response->setContent(file_get_contents($cachePath));
                $response->setStatusCode(200);
                $response->setHeader('Content-Type', 'application/json');
                return $response;
            }

            $json = $this->swagger->generate();

            // Cache the output
            $this->swagger->saveToFile($cachePath);

            $response->setContent($json);
            $response->setStatusCode(200);
            $response->setHeader('Content-Type', 'application/json');
            return $response;
        } catch (\Throwable $e) {
            $response->setStatusCode(500);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent((string)json_encode([
                'error' => 'API Documentation Generation Failed',
                'message' => $e->getMessage()
            ]));
            return $response;
        }
    }

    /**
     * Returns the Swagger UI.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function docs(\App\Core\Request $request, Response $response): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Project Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" >
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"> </script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"> </script>
    <script>
    window.onload = function() {
      const ui = SwaggerUIBundle({
        url: "/api/v1/swagger",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout"
      })
      window.ui = ui
    }
  </script>
</body>
</html>
HTML;
        $response->setContent($html);
        $response->setStatusCode(200);
        $response->setHeader('Content-Type', 'text/html');
        return $response;
    }
}

