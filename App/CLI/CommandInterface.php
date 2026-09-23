<?php
declare(strict_types=1);
namespace App\CLI;

interface CommandInterface
{
    public function handle(array $args): void;
}
