<?php
declare(strict_types=1);

/**
 * Framework helper functions.
 */

if (!function_exists('env')) {
    /**
     * Get an environment variable value.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        if ($value === 'false' || $value === '0' || $value === '') {
            return false;
        }
        if ($value === 'true' || $value === '1') {
            return true;
        }
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get a configuration value.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return \App\Core\Config::get($key, $default);
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with a status code.
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        throw new \App\Exceptions\ApiException($message, $code, null, $headers);
    }
}

if (!function_exists('response')) {
    /**
     * Create a JSON response.
     */
    function response(array $data, int $status = 200): \App\Core\Response
    {
        $res = new \App\Core\Response();
        $res->jsonResponse($data, $status);
        return $res;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage directory.
     */
    function storage_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/storage/' . $path;
    }
}
