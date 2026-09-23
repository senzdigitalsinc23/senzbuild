<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    protected string $basePath;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $layout = null;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__) . '/app/views';
    }

    /**
     * Render a view file, optionally using a layout.
     */
    public function render(string $view, array $data = []): string
    {
        $content = $this->renderFile($view, $data);

        if ($this->layout) {
            $layout = $this->layout;
            $this->layout = null; // Reset after use
            $content = $this->renderFile($layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /**
     * Render a view and return as a Response.
     */
    public function make(Response $response, string $view, array $data = []): Response
    {
        $response->setHeader('Content-Type', 'text/html');
        $response->setContent($this->render($view, $data));
        return $response;
    }

    /**
     * Specify a layout file.
     */
    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Start a section.
     */
    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * End a section.
     */
    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    /**
     * Output a section.
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Load and render a view file.
     */
    protected function renderFile(string $view, array $data = []): string
    {
        $file = rtrim($this->basePath, '/') . '/' . str_replace('.', '/', $view) . '.view.php';

        if (!file_exists($file)) {
            
            return response()->json([
                'title'     =>  'Error Page',
                'code'      =>  404,
                'success'   =>  false,
                'message'   =>  'View file not found: {$file}.'
            ]);
            exit;
            throw new \Exception("View file not found: {$file}");
        }

        extract($data);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
