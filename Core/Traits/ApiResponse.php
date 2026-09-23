<?php
declare(strict_types=1);

namespace App\Core\Traits;

use App\Core\Response;

trait ApiResponse
{
    /**
     * Standardized successful API response.
     *
     * @param mixed $data The data to return
     * @param string|null $message Optional success message
     * @param int $code HTTP status code
     * @return Response
     */
    protected function apiSuccess(mixed $data, ?string $message = null, int $code = 200): Response
    {
        $response = new Response();
        return $response->jsonResponse([
            'success' => true,
            'message' => $message ?? 'Operation completed successfully',
            'data' => $data,
        ], $code);
    }

    /**
     * Standardized error API response.
     *
     * @param string $message The error message
     * @param mixed $errors Optional detailed error list (e.g. validation errors)
     * @param int $code HTTP status code
     * @return Response
     */
    protected function apiError(string $message, mixed $errors = null, int $code = 400): Response
    {
        $response = new Response();
        return $response->jsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
