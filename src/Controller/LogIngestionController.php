<?php

namespace App\Controller;

use App\DTO\LogBatchRequest;
use App\Exception\ValidationException;
use App\Message\LogMessage;
use App\Service\BatchIdGenerator;
use App\Service\LogValidator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Routing\Attribute\Route;

class LogIngestionController extends AbstractController
{
    private LogValidator $validator;
    private BatchIdGenerator $batchIdGenerator;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;

    public function __construct(
        LogValidator $validator,
        BatchIdGenerator $batchIdGenerator,
        MessageBusInterface $messageBus,
        LoggerInterface $logger
    ) {
        $this->validator = $validator;
        $this->batchIdGenerator = $batchIdGenerator;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    #[Route('/api/logs/ingest', name: 'api_logs_ingest', methods: ['POST'])]
    public function ingest(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $this->logger->info('Ingest request received', ['data' => $data]);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON', ['error' => json_last_error_msg()]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON format'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['logs'])) {
                $this->logger->error('Missing logs field');
                return $this->json([
                    'status' => 'error',
                    'message' => 'Field "logs" is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['logs'])) {
                $this->logger->error('Empty logs array');
                return $this->json([
                    'status' => 'error',
                    'message' => 'Logs array cannot be empty'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (count($data['logs']) > 1000) {
                $this->logger->error('Batch size exceeded', ['count' => count($data['logs'])]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Maximum 1000 logs per batch'
                ], Response::HTTP_BAD_REQUEST);
            }

            $logBatchRequest = new LogBatchRequest($data);

            try {
                $this->validator->validate($logBatchRequest);
            } catch (ValidationException $e) {
                $this->logger->warning('Validation failed', ['errors' => $e->getErrors()]);
                return $this->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors()
                ], Response::HTTP_BAD_REQUEST);
            }

            $batchId = $this->batchIdGenerator->generate();
            $logsCount = count($logBatchRequest->logs);

            $this->logger->info('Processing logs', ['batch_id' => $batchId, 'count' => $logsCount]);

            foreach ($logBatchRequest->logs as $index => $log) {
                $logData = [
                    'timestamp' => $log->timestamp,
                    'level' => $log->level,
                    'service' => $log->service,
                    'message' => $log->message,
                    'context' => $log->context,
                    'trace_id' => $log->trace_id,
                ];

                $message = new LogMessage($batchId, $logData);

                $this->logger->debug('Dispatching message', ['index' => $index, 'log' => $logData]);

                try {
                    $this->messageBus->dispatch($message);
                } catch (TransportException $e) {
                    $this->logger->error('RabbitMQ transport error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            $this->logger->info('Successfully processed', ['batch_id' => $batchId, 'count' => $logsCount]);

            return $this->json([
                'status' => 'accepted',
                'batch_id' => $batchId,
                'logs_count' => $logsCount
            ], Response::HTTP_ACCEPTED);

        } catch (TransportException $e) {
            $this->logger->critical('RabbitMQ connection failed', [
                'error' => $e->getMessage(),
                'dsn' => $_ENV['MESSENGER_TRANSPORT_DSN'] ?? 'not set'
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Message broker unavailable'
            ], $_ENV['APP_ENV'] === 'dev' ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
