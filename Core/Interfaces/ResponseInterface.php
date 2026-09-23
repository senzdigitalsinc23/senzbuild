<?php
declare(strict_types=1);

namespace App\Core\Interfaces;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * ResponseInterface
 *
 * Defines the contract for an HTTP response.
 * Extends PSR-7 ResponseInterface for ecosystem compatibility.
 */
interface ResponseInterface extends PsrResponseInterface
{
    public function setStatusCode(int $code): void;
    public function setHeader(string $key, string $value): void;
    public function setContent(string $content): void;
    public function send(): void;
    public function json(array $data, int $statusCode = 200): void;
    public function jsonPaginated(
        array $data,
        int $total,
        int $page,
        int $perPage,
        string $message = 'Success',
        int $statusCode = 200
    ): void;
}
