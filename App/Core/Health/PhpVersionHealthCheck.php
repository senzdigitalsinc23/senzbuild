<?php
declare(strict_types=1);

namespace App\Core\Health;

class PhpVersionHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'php_version';
    }

    public function check(): array
    {
        $version = PHP_VERSION;
        $required = '8.2.0';
        $status = version_compare($version, $required, '>=') ? 'healthy' : 'warning';

        return [
            'status'  => $status,
            'message' => "PHP version: {$version}",
            'version' => $version,
            'required' => $required,
            'extensions' => [
                'pdo'        => extension_loaded('pdo'),
                'mbstring'   => extension_loaded('mbstring'),
                'openssl'    => extension_loaded('openssl'),
                'json'       => extension_loaded('json'),
                'tokenizer'  => extension_loaded('tokenizer'),
            ],
        ];
    }
}
