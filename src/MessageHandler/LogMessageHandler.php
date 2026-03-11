<?php

namespace App\MessageHandler;

use App\Message\LogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LogMessageHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {}

    public function __invoke(LogMessage $message): void
    {
        $this->logger->info('Processing log message', [
            'batch_id' => $message->getBatchId(),
            'log' => $message->getLogData(),
            'retry_count' => $message->getRetryCount()
        ]);

        sleep(1);
    }
}
