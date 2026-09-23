<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Enhanced View with Blade-like syntax support.
 *
 * Features:
 *   @extends('layout')  — Template inheritance
 *   @section('name') ... @endsection — Content sections
 *   @slot('name') ... @endslot — Named slots
 *   @component('name') ... @endcomponent — Reusable components
 *   {{ $var }} — Auto-escaped output
 *   {!! $var !!} — Raw output
 *   @foreach($items as $item) ... @endforeach
 *   @if($condition) ... @elseif(...) ... @else ... @endif
 *   @include('partial') — Include subviews
 *   @verbatim ... @endverbatim — Raw HTML output
 */
class View extends \App\Core\View
{
    /** @var array<string, string> Registered component closures */
    protected static array $components = [];

    /** @var array<string, array> Current slot stack */
    protected array $slots = [];

    /** @var array<string, mixed> Shared data across views */
    protected static array $sharedData = [];

    /**
     * Render with Blade-like syntax compilation.
     */
    public function render(string $view, array $data = []): string
    {
        // Check cache first
        $cachedPath = \App\Core\ViewCache::getCachedPath($view);
        if ($cachedPath !== null && !env('APP_DEBUG', false)) {
            return require $cachedPath;
        }

        $content = $this->compileBlade(
            file_get_contents($this->getViewPath($view)),
            $data
        );

        // Compile and cache
        if (!env('APP_DEBUG', false)) {
            \App\Core\ViewCache::compile($this->getViewPath($view), $view);
        }

        return $content;
    }

    /**
     * Compile Blade-like syntax to PHP.
     */
    protected function compileBlade(string $contents, array $data = []): string
    {
        // Store data as PHP variables
        $output = '<?php extract($data); ?>' . "\n";

        // Handle @verbatim blocks first (raw output)
        $contents = preg_replace_callback(
            '/@verbatim\s*(.*?)\s*@endverbatim/s',
            function ($matches) {
                return "<?php echo " . json_encode($matches[1]) . "; ?>";
            },
            $contents
        );

        // Handle @component ... @endcomponent
        $contents = preg_replace_callback(
            '/@component\s*\(([^)]+)\)\s*(.*?)\s*@endcomponent/s',
            function ($matches) use ($data) {
                $componentName = trim($matches[1], "'\"");
                $slotContent = $matches[2];

                // Compile slot content
                $compiledSlot = $this->compileBlade($slotContent, $data);

                // Render component
                $componentClass = '\\App\\View\\Components\\' . ucfirst($componentName);
                if (class_exists($componentClass)) {
                    $instance = new $componentClass();
                    return $instance->render($compiledSlot);
                }

                // Fallback: render from view file
                return $this->render($componentName, array_merge($data, ['slot' => $compiledSlot]));
            },
            $contents
        );

        // Handle @foreach
        $contents = preg_replace('/@foreach\s*\(([^)]+)\)\s*as\s*\(([^)]+)\)/', '<?php foreach($1 as $2): ?>', $contents);
        $contents = preg_replace('/@endforeach/', '<?php endforeach; ?>', $contents);

        // Handle @forelse
        $contents = preg_replace('/@forelse\s*\(([^)]+)\)\s*as\s*\(([^)]+)\)/', '<?php $foreachLoop = 0; foreach($1 as $2): $foreachLoop++; ?>', $contents);
        $contents = preg_replace('/@empty\s*\)/', '<?php endforelse: if ($foreachLoop === 0): ?>', $contents);
        $contents = preg_replace('/@endforelse/', '<?php endforeach; ?>', $contents);

        // Handle @if / @elseif / @else / @endif
        $contents = preg_replace('/@if\s*\(([^)]+)\)/', '<?php if($1): ?>', $contents);
        $contents = preg_replace('/@elseif\s*\(([^)]+)\)/', '<?php elseif($1): ?>', $contents);
        $contents = preg_replace('/@else/', '<?php else: ?>', $contents);
        $contents = preg_replace('/@endif/', '<?php endif; ?>', $contents);

        // Handle @while
        $contents = preg_replace('/@while\s*\(([^)]+)\)/', '<?php while($1): ?>', $contents);
        $contents = preg_replace('/@endwhile/', '<?php endwhile; ?>', $contents);

        // Handle @for
        $contents = preg_replace('/@for\s*\(([^)]+)\)/', '<?php for($1): ?>', $contents);
        $contents = preg_replace('/@endfor/', '<?php endfor; ?>', $contents);

        // Handle @switch / @case / @break / @default / @endswitch
        $contents = preg_replace('/@switch\s*\(([^)]+)\)/', '<?php switch($1): ?>', $contents);
        $contents = preg_replace('/@case\s*([^:]+):/', '<?php case $1: ?>', $contents);
        $contents = preg_replace('/@break/', '<?php break; ?>', $contents);
        $contents = preg_replace('/@default/', '<?php default: ?>', $contents);
        $contents = preg_replace('/@endswitch/', '<?php endswitch; ?>', $contents);

        // Handle @auth / @guest
        $contents = preg_replace('/@auth/', '<?php if(auth()->check()): ?>', $contents);
        $contents = preg_replace('/@guest/', '<?php if(!auth()->check()): ?>', $contents);
        $contents = preg_replace('/@endauth/', '<?php endif; ?>', $contents);
        $contents = preg_replace('/@endguest/', '<?php endif; ?>', $contents);

        // Handle @csrf
        $contents = preg_replace('/@csrf/', "<?php echo csrf_field(); ?>", $contents);

        // Handle @yield (for layout sections)
        $contents = preg_replace('/@yield\s*\(([^)]+)\)/', '<?php echo isset($sections[trim($1, "\"\'")]) ? $sections[trim($1, "\"\'")] : ""; ?>', $contents);

        // Handle @section
        $contents = preg_replace('/@section\s*\(([^)]+)\)/', '<?php ob_start(); ?>', $contents);
        $contents = preg_replace('/@endsection/', '<?php $sections[trim($1, "\"\'")] = ob_get_clean(); ?>', $contents);

        // Handle @include
        $contents = preg_replace_callback(
            '/@include\s*\(([^)]+)\)/',
            function ($matches) use ($data) {
                $view = trim($matches[1], "'\"");
                return '<?php echo $this->render("' . $view . '", $data); ?>';
            },
            $contents
        );

        // Handle {{ }} — escaped output
        $contents = preg_replace('/\{\{\{\s*([^}]+)\s*\}\}\}/', '<?php echo e($1); ?>', $contents);
        $contents = preg_replace('/\{\{\s*([^}]+)\s*\}\}/', '<?php echo e($1); ?>', $contents);

        // Handle {!! !!} — raw output
        $contents = preg_replace('/\{!!\s*([^}]+)\s*\!!\}/', '<?php echo $1; ?>', $contents);

        // Handle @json
        $contents = preg_replace('/@json\s*\(([^)]+)\)/', '<?php echo json_encode($1); ?>', $contents);

        // Handle comments
        $contents = preg_replace('/{{--\s*(.*?)\s*--}}/s', '', $contents);

        // Handle PHP tags directly
        $contents = preg_replace('/<\?php\s+/', '', $contents);

        return $output . $contents;
    }

    /**
     * Register a view component.
     *
     * @param string $name Component name
     * @param callable $callback Closure receiving compiled slot content
     */
    public static function component(string $name, callable $callback): void
    {
        self::$components[$name] = $callback;
    }

    /**
     * Share data across all views.
     *
     * @param string $key Data key
     * @param mixed $value Data value
     */
    public static function share(string $key, mixed $value): void
    {
        self::$sharedData[$key] = $value;
    }

    /**
     * Get shared data.
     */
    public static function getShared(): array
    {
        return self::$sharedData;
    }

    /**
     * Clear shared data.
     */
    public static function flushShared(): void
    {
        self::$sharedData = [];
    }

    /**
     * Get the full path to a view file.
     */
    protected function getViewPath(string $view): string
    {
        $paths = [
            dirname(__DIR__) . '/resources/views/' . $view . '.php',
            dirname(__DIR__) . '/app/views/' . $view . '.php',
            $this->basePath . '/' . $view . '.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new \InvalidArgumentException("View [{$view}] not found.");
    }
}
