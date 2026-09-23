<?php
declare(strict_types=1);

namespace Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use App\Storage\LocalFileStorage;

class LocalFileStorageTest extends TestCase
{
    private string $tempDir;
    private LocalFileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/framework_test_' . uniqid();
        $this->storage = new LocalFileStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $this->deleteDir($this->tempDir);
    }

    public function test_put_and_get(): void
    {
        $result = $this->storage->put('hello.txt', 'Hello, world!');

        $this->assertSame('hello.txt', $result['path']);
        $this->assertSame(13, $result['size']);
        $this->assertSame('text/plain', $result['mime']);

        $content = $this->storage->get('hello.txt');
        $this->assertSame('Hello, world!', $content);
    }

    public function test_exists(): void
    {
        $this->assertFalse($this->storage->exists('missing.txt'));

        $this->storage->put('exists.txt', 'data');
        $this->assertTrue($this->storage->exists('exists.txt'));
    }

    public function test_delete(): void
    {
        $this->storage->put('delete-me.txt', 'bye');
        $this->assertTrue($this->storage->delete('delete-me.txt'));
        $this->assertFalse($this->storage->exists('delete-me.txt'));
    }

    public function test_delete_nonexistent_returns_true(): void
    {
        // Deleting a non-existent file should not throw
        $this->assertTrue($this->storage->delete('nonexistent.txt'));
    }

    public function test_url(): void
    {
        $url = $this->storage->url('photos/image.png');
        $this->assertSame('/storage/photos/image.png', $url);
    }

    public function test_size(): void
    {
        $this->storage->put('big.bin', str_repeat('x', 1024));
        $size = $this->storage->size('big.bin');
        $this->assertSame(1024, $size);
    }

    public function test_size_returns_false_for_missing(): void
    {
        $this->assertFalse($this->storage->size('missing.bin'));
    }

    public function test_mkdir_for_nested_paths(): void
    {
        $result = $this->storage->put('a/b/c/deep.txt', 'deep content');
        $this->assertTrue($this->storage->exists('a/b/c/deep.txt'));
        $this->assertSame('deep content', $this->storage->get('a/b/c/deep.txt'));
    }

    public function test_directory_traversal_blocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->storage->put('../../etc/passwd', 'evil');
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
