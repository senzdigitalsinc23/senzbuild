<?php
// app/Support/Security.php
namespace App\Support;

final class Security
{
    public static function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return substr($name, 0, 120);
    }

    public static function validateUpload(array $file, array $allowedMime, int $maxBytes): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('File too large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            throw new \RuntimeException('Invalid file type');
        }
    }
}
