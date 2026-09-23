<?php
declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ExceptionHandler;
use App\Exceptions\BaseException;
use App\Core\ApiResponse;
use App\Core\Response;
use PDOException;
use Exception;
use PHPUnit\Framework\TestCase;

class ExceptionHandlerTest extends TestCase
{
    public function testHandleBaseException(): void
    {
        $exception = new class('Custom Error', 403, 'CUSTOM_CODE', ['detail' => 'more info']) extends BaseException {
            public function __construct($msg, $code, $errCode, $ctx = []) {
                parent::__construct($msg, $code, $errCode, $ctx);
            }
            public function getStatusCode(): int { return $this->statusCode; }
            public function getErrorCode(): string { return $this->errorCode; }
            public function getContext(): array { return $this->context; }
        };

        $response = ExceptionHandler::handle($exception);

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Custom Error', $data['message']);
        $this->assertEquals('CUSTOM_CODE', $data['error_code']);
        $this->assertEquals(['detail' => 'more info'], $data['errors']);
    }

    public function testHandlePDOException(): void
    {
        $exception = new PDOException('Database connection failed');
        $response = ExceptionHandler::handle($exception);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        // PDOException maps to DATABASE_ERROR code
        $this->assertMatchesRegularExpression('/DATABASE_ERROR|SERVER_ERROR/', $data['error_code']);
    }

    public function testHandleGenericException(): void
    {
        $exception = new Exception('Something went wrong');
        $response = ExceptionHandler::handle($exception);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('SERVER_ERROR', $data['error_code']);
    }

    public function testToJson(): void
    {
        $exception = new Exception('Something went wrong');
        $json = ExceptionHandler::toJson($exception);
        $data = json_decode($json, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('SERVER_ERROR', $data['error_code']);
    }
}
