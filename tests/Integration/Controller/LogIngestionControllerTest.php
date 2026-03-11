<?php

namespace App\Tests\Integration\Controller;

use App\Message\LogMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class LogIngestionControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['MESSENGER_TRANSPORT_DSN'] = 'in-memory://';
    }

    public function testSuccessfulLogIngestion(): void
    {
        $client = static::createClient();

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

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertEquals('accepted', $content['status']);
        $this->assertArrayHasKey('batch_id', $content);
        $this->assertEquals(2, $content['logs_count']);
        $this->assertStringStartsWith('batch_', $content['batch_id']);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.in_memory');

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
        $client = static::createClient();

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

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertEquals('accepted', $content['status']);
        $this->assertEquals(1, $content['logs_count']);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.in_memory');
        $messages = $transport->get();
        $this->assertCount(1, $messages);
    }

    public function testInvalidJsonRequest(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Invalid JSON format', $content['message']);
    }

    public function testMissingLogsField(): void
    {
        $client = static::createClient();

        $logData = [
            'something' => 'else'
        ];

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Field "logs" is required', $content['message']);
    }

    public function testEmptyLogsArray(): void
    {
        $client = static::createClient();

        $logData = ['logs' => []];

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Logs array cannot be empty', $content['message']);
    }

    public function testBatchSizeExceeded(): void
    {
        $client = static::createClient();

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

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Maximum 1000 logs per batch', $content['message']);
    }

    public function testMissingRequiredField(): void
    {
        $client = static::createClient();

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

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('Validation failed', $content['message']);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);
    }

    public function testBatchIdIsUnique(): void
    {
        $client = static::createClient();

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

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );
        $response1 = json_decode($client->getResponse()->getContent(), true);
        $batchId1 = $response1['batch_id'];

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );
        $response2 = json_decode($client->getResponse()->getContent(), true);
        $batchId2 = $response2['batch_id'];

        $this->assertNotEquals($batchId1, $batchId2);
    }

    public function testMessagesHaveCorrectMetadata(): void
    {
        $client = static::createClient();

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

        $client->request(
            'POST',
            '/api/logs/ingest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($logData)
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.in_memory');
        $messages = $transport->get();

        $this->assertCount(1, $messages);

        $envelope = $messages[0];
        $message = $envelope->getMessage();

        $this->assertInstanceOf(LogMessage::class, $message);
        $this->assertEquals($response['batch_id'], $message->getBatchId());
        $this->assertInstanceOf(\DateTimeInterface::class, $message->getPublishedAt());

        $logDataFromMessage = $message->getLogData();
        $this->assertEquals('error', $logDataFromMessage['level']);
        $this->assertEquals('auth-service', $logDataFromMessage['service']);
        $this->assertEquals('Test', $logDataFromMessage['message']);
    }
}
