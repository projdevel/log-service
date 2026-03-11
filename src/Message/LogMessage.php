<?php

namespace App\Message;

/**
 * Message for the log
 */
class LogMessage
{
    private string $batchId;
    private array $logData;
    private \DateTimeInterface $publishedAt;
    private int $retryCount = 0;

    public function __construct(string $batchId, array $logData)
    {
        $this->batchId = $batchId;
        $this->logData = $logData;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function getLogData(): array
    {
        return $this->logData;
    }

    public function getPublishedAt(): \DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function incrementRetryCount(): void
    {
        $this->retryCount++;
    }
}
