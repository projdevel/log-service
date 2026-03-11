<?php

namespace App\Tests\Integration\Controller;

use App\Message\LogMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LogIngestionControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $_ENV['MESSENGER_TRANSPORT_DSN'] = 'in-memory://';
    }

    public function testSuccessfulLogIngestion(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'User authentication failed',
                    'context' => [
                        'user_id' => 123,
                        'ip' => '192.168.1.1',
                        'error_code' => 'INVALID_TOKEN'
                    ],
                    'trace_id' => 'abc123def456'
                ],
                [
                    'timestamp' => '2026-02-26T10:30:46Z',
                    'level' => 'info',
                    'service' => 'api-gateway',
                    'message' => 'Request processed',
                    'context' => [
                        'endpoint' => '/api/users',
                        'method' => 'GET',
                        'response_time_ms' => 145
                    ],
                    'trace_id' => 'abc123def456'
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertEquals('accepted', $content['status']);
        $this->assertArrayHasKey('batch_id', $content);
        $this->assertEquals(2, $content['logs_count']);
        $this->assertStringStartsWith('batch_', $content['batch_id']);

        $transport = self::getContainer()->get('messenger.transport.in_memory');
        $messages = $transport->get();

        $this->assertCount(2, $messages);

        foreach ($messages as $envelope) {
            $message = $envelope->getMessage();
            $this->assertInstanceOf(LogMessage::class, $message);
            $this->assertEquals($content['batch_id'], $message->getBatchId());
            $this->assertInstanceOf(\DateTimeInterface::class, $message->getPublishedAt());
        }
    }

    public function testSingleLogIngestion(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'Test log'
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertEquals(1, $content['logs_count']);

        // Проверяем транспорт
        $transport = self::getContainer()->get('messenger.transport.in_memory');
        $messages = $transport->get();
        $this->assertCount(1, $messages);
    }

    public function testInvalidJsonRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Invalid JSON format', $content['message']);
    }

    public function testMissingLogsField(): void
    {
        $logData = [
            'something' => 'else'
        ];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Field "logs" is required', $content['message']);
    }

    public function testEmptyLogsArray(): void
    {
        $logData = ['logs' => []];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Logs array cannot be empty', $content['message']);
    }

    public function testBatchSizeExceeded(): void
    {
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

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Maximum 1000 logs per batch', $content['message']);
    }

    public function testMissingRequiredField(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'message' => 'User authentication failed'
                    // missing service
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Validation failed', $content['message']);
    }

    public function testBatchIdIsUnique(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'info',
                    'service' => 'test',
                    'message' => 'Test 1'
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );
        $response1 = json_decode($this->client->getResponse()->getContent(), true);
        $batchId1 = $response1['batch_id'];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );
        $response2 = json_decode($this->client->getResponse()->getContent(), true);
        $batchId2 = $response2['batch_id'];

        $this->assertNotEquals($batchId1, $batchId2);
    }

    public function testMessagesHaveCorrectMetadata(): void
    {
        $logData = [
            'logs' => [
                [
                    'timestamp' => '2026-02-26T10:30:45Z',
                    'level' => 'error',
                    'service' => 'auth-service',
                    'message' => 'Test'
                ]
            ]
        ];

        $this->client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $transport = self::getContainer()->get('messenger.transport.in_memory');
        $messages = $transport->get();

        $this->assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        $this->assertInstanceOf(LogMessage::class, $message);
        $this->assertEquals($response['batch_id'], $message->getBatchId());
        $this->assertInstanceOf(\DateTimeInterface::class, $message->getPublishedAt());
        $this->assertEquals(0, $message->getRetryCount());
    }
}
