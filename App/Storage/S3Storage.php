<?php
declare(strict_types=1);

namespace App\Storage;

use App\Interfaces\FileStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;

/**
 * Amazon S3 (or S3-compatible) implementation of FileStorage.
 *
 * Requires aws/aws-sdk-php or Guzzle with an S3 client.
 * Falls back gracefully if the SDK is not installed.
 */
class S3Storage implements FileStorage
{
    protected Client $client;
    protected string $bucket;
    protected string $region;
    protected string $baseUrl;
    protected array $mimes;
    protected ?string $acl;

    public function __construct(
        Client   $client,
        string   $bucket,
        string   $region,
        ?string  $baseUrl = null,
        ?string  $acl     = null
    ) {
        $this->client   = $client;
        $this->bucket   = $bucket;
        $this->region   = $region;
        $this->baseUrl  = $baseUrl ?? "https://{$bucket}.s3.{$region}.amazonaws.com";
        $this->acl      = $acl;
        $this->mimes    = require __DIR__ . '/../../config/storage_mimes.php';
    }

    public function put(string $path, string $contents, array $options = []): array
    {
        $key     = ltrim($path, '/');
        $mime    = $options['mime'] ?? 'application/octet-stream';
        $public  = $options['public'] ?? $this->acl === 'public-read';

        $params = [
            'Bucket' => $this->bucket,
            'Key'    => $key,
            'Body'   => Utils::streamFor($contents),
            'ContentType' => $mime,
        ];

        if ($public) {
            $params['ACL'] = 'public-read';
        }

        $this->client->putObject($params);

        return [
            'path' => $path,
            'size' => strlen($contents),
            'mime' => $mime,
            'url'  => $this->url($path),
        ];
    }

    public function get(string $path): string|false
    {
        $key = ltrim($path, '/');
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            return (string)$result['Body'];
        } catch (\Throwable) {
            return false;
        }
    }

    public function exists(string $path): bool
    {
        $key = ltrim($path, '/');
        try {
            $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        $key = ltrim($path, '/');
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function url(string $path, int $expireSeconds = 0): string
    {
        $key = ltrim($path, '/');
        if ($expireSeconds > 0) {
            // Generate a presigned URL
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            $request = $this->client->createPresignedRequest($cmd, "+" . $expireSeconds . " seconds");
            return (string)$request->getUri();
        }
        return $this->baseUrl . '/' . $key;
    }

    public function size(string $path): int|false
    {
        $key = ltrim($path, '/');
        try {
            $result = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);
            return (int)$result['ContentLength'];
        } catch (\Throwable) {
            return false;
        }
    }
}
