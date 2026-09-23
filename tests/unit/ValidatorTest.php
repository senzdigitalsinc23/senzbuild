<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredValidation(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];
        $validator = new Validator($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testStringValidation(): void
    {
        $data = ['name' => 123];
        $rules = ['name' => 'string'];
        $validator = new Validator($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testEmailValidation(): void
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => 'email'];
        $validator = new Validator($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function testMinLengthValidation(): void
    {
        $data = ['password' => '123'];
        $rules = ['password' => 'min:5'];
        $validator = new Validator($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors());
    }

    public function testMaxLengthValidation(): void
    {
        $data = ['username' => 'this_is_way_too_long_for_the_limit'];
        $rules = ['username' => 'max:10'];
        $validator = new Validator($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('username', $validator->errors());
    }

    public function testCustomMessages(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];
        $customMessages = ['name.required' => 'Name is strictly required!'];
        $validator = new Validator($data, $rules, null, $customMessages);

        $this->assertEquals('Name is strictly required!', $validator->errors()['name'][0]);
    }

    public function testBailRule(): void
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => 'bail|required|email'];
        // If email is required but not email, bail should stop after the first failure
        // Actually, since it's present but invalid email, required passes.

        $data = ['email' => ''];
        $validator = new Validator($data, $rules);

        $this->assertTrue($validator->fails());
        // Only 'required' should have triggered, not 'email' because of bail
        $this->assertCount(1, $validator->errors()['email']);
    }
}
