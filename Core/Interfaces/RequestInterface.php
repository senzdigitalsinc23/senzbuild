<?php
declare(strict_types=1);

namespace App\Core\Interfaces;

use Psr\Http\Message\ServerRequestInterface;

/**
 * RequestInterface
 *
 * Defines the contract for an HTTP request.
 * Extends PSR-7 ServerRequestInterface for ecosystem compatibility.
 */
interface RequestInterface extends ServerRequestInterface
{
    public function getPath(): string;
    public function getQuery(?string $key = null, mixed $default = null): mixed;
    public function getPost(?string $key = null, mixed $default = null): mixed;
    public function getFiles(?string $key = null): mixed;
    public function setAttribute($key, $value): void;
    public function getAttribute($key, $default = null);
    public function input(string $key, $default = null);
    public function getBodyParams(): array;
}
