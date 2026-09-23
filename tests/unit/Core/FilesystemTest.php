<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Filesystem;
use App\Core\LocalFilesystemAdapter;

class FilesystemTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/fs_test_' . uniqid();
        mkdir("{$this->tempDir}/app", 0755, true);
        Filesystem::extend('local', new LocalFilesystemAdapter("{$this->tempDir}/app"));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        Filesystem::extend('local', new LocalFilesystemAdapter(sys_get_temp_dir()));
    }

    public function test_write_and_read(): void
    {
        Filesystem::write('local', 'test.txt', 'Hello World');
        $content = Filesystem::read('local', 'test.txt');
        $this->assertSame('Hello World', $content);
    }

    public function test_exists(): void
    {
        Filesystem::write('local', 'exists.txt', 'data');
        $this->assertTrue(Filesystem::exists('local', 'exists.txt'));
        $this->assertFalse(Filesystem::exists('local', 'missing.txt'));
    }

    public function test_delete(): void
    {
        Filesystem::write('local', 'delete.txt', 'data');
        $this->assertTrue(Filesystem::delete('local', 'delete.txt'));
        $this->assertFalse(Filesystem::exists('local', 'delete.txt'));
    }

    public function test_size(): void
    {
        Filesystem::write('local', 'size.txt', '12345');
        $this->assertSame(5, Filesystem::size('local', 'size.txt'));
    }

    public function test_list_contents(): void
    {
        Filesystem::write('local', 'a.txt', 'a');
        Filesystem::write('local', 'b.txt', 'b');
        $contents = Filesystem::listContents('local');
        $this->assertIsArray($contents);
        $paths = array_column($contents, 'path');
        $this->assertTrue(in_array('a.txt', $paths));
        $this->assertTrue(in_array('b.txt', $paths));
    }

    public function test_copy(): void
    {
        Filesystem::write('local', 'src.txt', 'copy me');
        Filesystem::copy('local', 'src.txt', 'local', 'dst.txt');
        $this->assertTrue(Filesystem::exists('local', 'dst.txt'));
        $this->assertSame('copy me', Filesystem::read('local', 'dst.txt'));
    }

    public function test_move(): void
    {
        Filesystem::write('local', 'src.txt', 'move me');
        Filesystem::move('local', 'src.txt', 'local', 'dst.txt');
        $this->assertFalse(Filesystem::exists('local', 'src.txt'));
        $this->assertTrue(Filesystem::exists('local', 'dst.txt'));
    }

    public function test_public_url(): void
    {
        $url = Filesystem::publicUrl('local', 'files/photo.jpg');
        $this->assertStringContainsString('storage/files/photo.jpg', $url);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob("{$dir}/*") as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }
}
