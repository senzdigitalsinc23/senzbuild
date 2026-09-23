<?php
declare(strict_types=1);

namespace Tests\Unit\ORM;

use PHPUnit\Framework\TestCase;
use Database\ORM\Traits\Encryptable;

class EncryptableTraitTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = bin2hex(random_bytes(32));
        $_ENV['ENCRYPTION_KEY'] = $this->key;
    }

    protected function tearDown(): void
    {
        unset($_ENV['ENCRYPTION_KEY']);
    }

    public function test_encrypt_and_decrypt_roundtrip(): void
    {
        $plain = 'secret-data-12345';
        $encrypted = TestEncryptable::encrypt($plain);
        $this->assertNotSame($plain, $encrypted);
        $this->assertNotFalse($encrypted);

        $decrypted = TestEncryptable::decrypt($encrypted);
        $this->assertSame($plain, $decrypted);
    }

    public function test_decrypt_fallback_returns_plain_when_no_key(): void
    {
        unset($_ENV['ENCRYPTION_KEY']);
        $plain = 'no-key-value';
        $encrypted = TestEncryptable::encrypt($plain);
        $this->assertSame($plain, $encrypted); // returns as-is when no key

        $result = TestEncryptable::decrypt($encrypted);
        $this->assertSame($plain, $result);
    }

    public function test_invalid_base64_returns_false(): void
    {
        $result = TestEncryptable::decrypt('not-valid-base64!!!');
        $this->assertFalse($result);
    }

    public function test_model_pre_save_encodes_attributes(): void
    {
        $model = new TestEncryptableModel([
            'name'  => 'John',
            'ssn'   => '123-45-6789',
            'phone' => '+1-555-0100',
        ]);
        $model->preSave();

        $this->assertNotSame('123-45-6789', $model->attributes['ssn']);
        $this->assertNotSame('+1-555-0100', $model->attributes['phone']);
        $this->assertSame('John', $model->attributes['name']); // non-encrypted field unchanged
    }

    public function test_model_sync_original_decodes_attributes(): void
    {
        $encryptedSsn = TestEncryptable::encrypt('123-45-6789');
        $encryptedPhone = TestEncryptable::encrypt('+1-555-0100');

        $model = new TestEncryptableModel([
            'name'  => 'John',
            'ssn'   => $encryptedSsn,
            'phone' => $encryptedPhone,
        ]);
        $model->syncOriginal();

        $this->assertSame('123-45-6789', $model->ssn);
        $this->assertSame('+1-555-0100', $model->phone);
        $this->assertSame('John', $model->name);
    }

    public function test_get_encryptable_fields(): void
    {
        $model = new TestEncryptableModel([]);
        $this->assertSame(['ssn', 'phone'], $model->getEncryptableFields());
    }
}

/** Minimal class that exposes the trait's static methods for testing */
class TestEncryptable
{
    use Encryptable;
}

/** Model-like class for testing the encryptable trait behaviour */
class TestEncryptableModel
{
    use Encryptable;

    public array $attributes = [];

    public function __construct(array $attributes, array $encryptable = ['ssn', 'phone'])
    {
        $this->attributes = $attributes;
        $this->encryptable = $encryptable;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
}
