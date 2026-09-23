<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Interfaces\ContainerInterface;

class Controller
{
    use \App\Core\Traits\ApiResponse;

    protected ?ContainerInterface $container = null;

    public function __construct(?ContainerInterface $container = null)
    {
        if ($container !== null) {
            $this->container = $container;
        }
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    protected function getRouter(): Router
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container not set on controller');
        }
        return $this->container->get(Router::class);
    }

    /**
     * Render a PHP view file and pass data to it.
     *
     * @param string $viewPath Relative to app/Views (e.g. 'users/show')
     * @param array $data Variables to extract in view
     * @return string Rendered HTML
     */
    protected function render(string $viewPath, array $data = []): string
    {
        $viewFile = dirname(__DIR__, 2) . '/app/views/' . str_replace('.', '/', $viewPath) . '.php';

        if (!file_exists($viewFile)) {
            throw new \Exception("View file {$viewFile} not found.");
        }

        // Extract data variables into scope
        extract($data, EXTR_SKIP);

        // Start output buffering
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    /**
     * Generate URL by route name
     */
    protected function route(string $name, array $params = []): string
    {
        return $this->getRouter()->generateUri($name, $params) ?? '#';
    }

    /**
     * Simple helper to redirect
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }
}
