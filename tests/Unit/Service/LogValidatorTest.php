<?php

namespace App\Tests\Unit\Service;

use App\DTO\LogBatchRequest;
use App\Exception\ValidationException;
use App\Service\LogValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class LogValidatorTest extends TestCase
{
    private LogValidator $logValidator;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->logValidator = new LogValidator($validator);
    }

    public function testValidLogBatchPassesValidation(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'User authentication failed',
                    'context' => ['user_id' => 123],
                    'trace_id' => 'abc123'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);

        $this->assertTrue(true);
    }

    public function testValidatesMultipleLogs(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'Error log'
                ],
                [
                    'timestamp' => '2026-02-26T10:30:46Z',
                    'level' => 'info',
                    'service' => 'api-gateway',
                    'message' => 'Info log'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);

        $this->assertTrue(true);
    }

    public function testMissingTimestampThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $logData = [
            'logs' => [
                [
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'Test message'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);
    }

    public function testMissingLevelThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'service' => 'auth-service',
                    'message' => 'Test message'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);
    }

    public function testMissingServiceThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'message' => 'Test message'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);
    }

    public function testMissingMessageThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);
    }

    public function testBatchSizeLimitThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $logs = [];
        for ($i = 0; $i < 1001; $i++) {
            $logs[] = [
                'timestamp' => '2026-02-26T10:30:45Z',
                'level' => 'info',
                'service' => 'test-service',
                'message' => "Log message $i"
            ];
        }

        $logData = ['logs' => $logs];
        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);
    }

    public function testEmptyLogsArrayThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $logData = ['logs' => []];
        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);
    }

    public function testContextIsOptional(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'Test message'
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);

        $this->assertTrue(true);
    }

    public function testTraceIdIsOptional(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'Test message',
                    'context' => ['test' => true]
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);
        $this->logValidator->validate($request);

        $this->assertTrue(true);
    }

    public function testReturnsErrorMessages(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                ]
            ]
        ];

        $request = new LogBatchRequest($logData);

        try {
            $this->logValidator->validate($request);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $this->assertIsArray($errors);
            $this->assertStringContainsString('service', implode(' ', $errors));
            $this->assertStringContainsString('message', implode(' ', $errors));
        }
    }
}
