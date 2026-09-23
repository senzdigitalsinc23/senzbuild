<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\ConfigCache;

class ConfigCacheTest extends TestCase
{
    private string $tempDir;
    private string $configDir;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/config_cache_test_' . uniqid();
        $this->configDir = "{$this->tempDir}/config";
        $this->outputDir = "{$this->tempDir}/storage/config";
        mkdir($this->configDir, 0755, true);
        mkdir($this->outputDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_cache_generates_valid_php_file(): void
    {
        $this->createConfigFile('app.php', "<?php return ['name' => 'TestApp', 'debug' => true];");
        $this->createConfigFile('database.php', "<?php return ['host' => 'localhost', 'port' => 3306];");

        $path = ConfigCache::cache($this->configDir, $this->outputDir);

        $this->assertFileExists($path);
        $data = require $path;
        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('database', $data);
        $this->assertSame('TestApp', $data['app']['name']);
        $this->assertSame('localhost', $data['database']['host']);
    }

    public function test_has_cache_returns_false_before_generation(): void
    {
        $this->assertFalse(ConfigCache::hasCache($this->outputDir));
    }

    public function test_has_cache_returns_true_after_generation(): void
    {
        $this->createConfigFile('app.php', "<?php return ['name' => 'X'];");
        ConfigCache::cache($this->configDir, $this->outputDir);
        $this->assertTrue(ConfigCache::hasCache($this->outputDir));
    }

    public function test_clear_removes_cache_file(): void
    {
        $this->createConfigFile('app.php', "<?php return ['name' => 'X'];");
        ConfigCache::cache($this->configDir, $this->outputDir);
        $this->assertTrue(ConfigCache::hasCache($this->outputDir));

        ConfigCache::clear($this->outputDir);
        $this->assertFalse(ConfigCache::hasCache($this->outputDir));
    }

    public function test_get_path_returns_correct_path(): void
    {
        $path = ConfigCache::getPath($this->outputDir);
        $this->assertSame("{$this->outputDir}/cache.php", $path);
    }

    public function test_cache_is_idempotent(): void
    {
        $this->createConfigFile('app.php', "<?php return ['version' => 2];");

        $p1 = ConfigCache::cache($this->configDir, $this->outputDir);
        $p2 = ConfigCache::cache($this->configDir, $this->outputDir);

        $this->assertSame($p1, $p2);
        $data = require $p1;
        $this->assertSame(2, $data['app']['version']);
    }

    public function test_empty_config_dir_produces_empty_array(): void
    {
        $path = ConfigCache::cache($this->configDir, $this->outputDir);
        $data = require $path;
        $this->assertSame([], $data);
    }

    private function createConfigFile(string $name, string $content): void
    {
        file_put_contents("{$this->configDir}/{$name}", $content);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob("{$dir}/*") as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }
}
