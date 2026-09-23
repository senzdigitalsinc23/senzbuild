<?php

namespace Tests\Unit\Core;

use App\Core\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_response_structure(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $response = ApiResponse::success($data, 'Fetched successfully');

        $this->assertTrue($response['success']);
        $this->assertSame('Fetched successfully', $response['message']);
        $this->assertSame($data, $response['data']);
        $this->assertArrayNotHasKey('errors', $response);
    }

    public function test_error_response_structure(): void
    {
        $response = ApiResponse::error('Resource not found', null, 404);

        $this->assertFalse($response['success']);
        $this->assertSame('Resource not found', $response['error']);
        $this->assertArrayHasKey('message', $response);
    }

    public function test_success_with_pagination_meta(): void
    {
        $data = [['id' => 1], ['id' => 2]];
        $meta = [
            'current_page' => 1,
            'per_page'     => 15,
            'total'        => 42,
            'last_page'    => 3,
        ];
        $response = ApiResponse::paginated($data, $meta, 'Products retrieved');

        $this->assertTrue($response['success']);
        $this->assertSame($data, $response['data']);
        $this->assertSame($meta, $response['meta']);
        $this->assertSame('Products retrieved', $response['message']);
    }

    public function test_validation_error_response(): void
    {
        $errors = ['email' => 'The email field is required.'];
        $response = ApiResponse::validationError($errors);

        $this->assertFalse($response['success']);
        $this->assertSame('Validation failed', $response['message']);
        $this->assertSame($errors, $response['errors']);
        $this->assertSame(422, $response['status_code']);
    }
}
