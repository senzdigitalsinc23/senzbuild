<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Vault;

class VaultTest extends TestCase
{
    private string $vaultDir;

    protected function setUp(): void
    {
        $this->vaultDir = sys_get_temp_dir() . '/vault_test_' . uniqid();
        mkdir("{$this->vaultDir}/storage/vault", 0700, true);

        // Reset Vault static state to use our temp directory
        $this->resetVaultState();
    }

    protected function tearDown(): void
    {
        $this->resetVaultState();
        $this->removeDir($this->vaultDir);
    }

    public function test_set_and_get_roundtrip(): void
    {
        Vault::init();
        Vault::set('db.password', 'supersecret123');
        $result = Vault::get('db.password');
        $this->assertSame('supersecret123', $result);
    }

    public function test_set_and_get_complex_value(): void
    {
        $value = ['key' => 'val', 'nested' => ['a' => 1, 'b' => 2]];
        Vault::init();
        Vault::set('api.keys', $value);
        $result = Vault::get('api.keys');
        $this->assertSame($value, $result);
    }

    public function test_has_returns_true_after_set(): void
    {
        Vault::init();
        Vault::set('test.key', 'hello');
        $this->assertTrue(Vault::has('test.key'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        Vault::init();
        $this->assertFalse(Vault::has('nonexistent.key'));
    }

    public function test_forget_removes_secret(): void
    {
        Vault::init();
        Vault::set('temp.secret', 'value');
        $this->assertTrue(Vault::has('temp.secret'));

        Vault::forget('temp.secret');
        $this->assertFalse(Vault::has('temp.secret'));
    }

    public function test_flush_wipes_all_secrets(): void
    {
        Vault::init();
        Vault::set('a', '1');
        Vault::set('b', '2');
        $this->assertTrue(Vault::has('a'));
        $this->assertTrue(Vault::has('b'));

        Vault::flush();
        $this->assertFalse(Vault::has('a'));
        $this->assertFalse(Vault::has('b'));
    }

    public function test_encrypted_values_are_not_plaintext_in_file(): void
    {
        Vault::init();
        Vault::set('secret.ssh_key', 'ssh-rsa AAAAB3...');

        $ref = new \ReflectionClass(Vault::class);
        $vaultDirProp = $ref->getProperty('vaultDir');
        $vaultDirProp->setAccessible(true);
        $file = $vaultDirProp->getValue() . '/secrets.json';

        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringNotContainsString('ssh-rsa AAAAB3...', $content);
        $this->assertStringContainsString('secret', $content);
    }

    public function test_fallback_to_env_when_vault_empty(): void
    {
        putenv('VAULT_TEST_FALLBACK=env_value_123');
        Vault::init();
        $result = Vault::get('VAULT_TEST_FALLBACK', 'default');
        $this->assertSame('env_value_123', $result);
        putenv('VAULT_TEST_FALLBACK');
    }

    public function test_master_key_exists_after_init(): void
    {
        Vault::init();
        $key = Vault::getMasterKey();
        $this->assertNotNull($key);
        $this->assertNotEmpty($key);
    }

    public function test_clear_cache_does_not_affect_vault_file(): void
    {
        Vault::init();
        Vault::set('cache.test', 'data');
        $this->assertTrue(Vault::has('cache.test'));

        Vault::clearCache();
        $this->assertTrue(Vault::has('cache.test'));
    }

    public function test_multiple_keys_isolated(): void
    {
        Vault::init();
        Vault::set('k1', 'value_one');
        Vault::set('k2', 'value_two');
        Vault::set('k3', 'value_three');

        $this->assertSame('value_one', Vault::get('k1'));
        $this->assertSame('value_two', Vault::get('k2'));
        $this->assertSame('value_three', Vault::get('k3'));
    }

    public function test_empty_string_value(): void
    {
        Vault::init();
        Vault::set('empty', '');
        $this->assertSame('', Vault::get('empty'));
    }

    public function test_numeric_value(): void
    {
        Vault::init();
        Vault::set('port', 8080);
        $this->assertSame(8080, Vault::get('port'));
    }

    public function test_overwrite_existing_key(): void
    {
        Vault::init();
        Vault::set('overwrite', 'first');
        Vault::set('overwrite', 'second');
        $this->assertSame('second', Vault::get('overwrite'));
    }

    private function resetVaultState(): void
    {
        Vault::testReset();
        Vault::setVaultDir($this->vaultDir);
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
