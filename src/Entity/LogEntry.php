<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class LogEntry
{
    #[Assert\NotBlank(message: 'Timestamp is required')]
    #[Assert\DateTime(message: 'Invalid timestamp format. Use ISO 8601 format (e.g., 2026-02-26T10:30:45Z)')]
    public string $timestamp;

    #[Assert\NotBlank(message: 'Level is required')]
    #[Assert\Choice(
        choices: ['debug', 'info', 'warning', 'error', 'critical'],
        message: 'Invalid log level. Choose one of: debug, info, warning, error, critical'
    )]
    public string $level;

    #[Assert\NotBlank(message: 'Service is required')]
    public string $service;

    #[Assert\NotBlank(message: 'Message is required')]
    public string $message;

    #[Assert\Type(type: 'array', message: 'Context must be an array')]
    public ?array $context = null;

    public ?string $trace_id = null;

    public function __construct(array $data)
    {
        $this->timestamp = $data['timestamp'] ?? '';
        $this->level = $data['level'] ?? '';
        $this->service = $data['service'] ?? '';
        $this->message = $data['message'] ?? '';
        $this->context = $data['context'] ?? null;
        $this->trace_id = $data['trace_id'] ?? null;
    }
}
