<?php

namespace App\Service;

use App\DTO\LogBatchRequest;
use App\Exception\ValidationException;

/**
 * Validates log batch requests
 * Handles all business logic validation rules
 */
class LogValidator
{
    private const MAX_BATCH_SIZE = 1000;

    public function validate(LogBatchRequest $request): void
    {
        $errors = [];

        if (empty($request->logs)) {
            $errors[] = 'Logs array cannot be empty';
            throw new ValidationException('Validation failed: ' . implode(', ', $errors), $errors);
        }

        if (count($request->logs) > self::MAX_BATCH_SIZE) {
            $errors[] = sprintf('Maximum %d logs per batch', self::MAX_BATCH_SIZE);
        }

        foreach ($request->logs as $index => $log) {
            $this->validateLog($log, $index, $errors);
        }

        if (!empty($errors)) {
            throw new ValidationException(
                'Validation failed: ' . implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '...' : ''),
                $errors
            );
        }
    }

    private function validateLog($log, int $index, array &$errors): void
    {
        if (empty($log->timestamp)) {
            $errors[] = "Log #$index: timestamp is required";
        } elseif (!$this->isValidTimestamp($log->timestamp)) {
            $errors[] = "Log #$index: timestamp must be in ISO 8601 format (e.g., 2024-01-01T12:00:00Z)";
        }

        if (empty($log->level)) {
            $errors[] = "Log #$index: level is required";
        } elseif (!$this->isValidLogLevel($log->level)) {
            $errors[] = "Log #$index: level must be one of: debug, info, notice, warning, error, critical, alert, emergency";
        }

        if (empty($log->service)) {
            $errors[] = "Log #$index: service is required";
        } elseif (strlen($log->service) > 255) {
            $errors[] = "Log #$index: service name too long (maximum 255 characters)";
        }

        if (empty($log->message)) {
            $errors[] = "Log #$index: message is required";
        } elseif (strlen($log->message) > 65535) {
            $errors[] = "Log #$index: message too long (maximum 65535 characters)";
        }

        if (!empty($log->trace_id) && strlen($log->trace_id) > 255) {
            $errors[] = "Log #$index: trace_id too long (maximum 255 characters)";
        }

        if ($log->context !== null && !is_array($log->context)) {
            $errors[] = "Log #$index: context must be an array or null";
        }
    }

    private function isValidTimestamp(string $timestamp): bool
    {
        $date = \DateTime::createFromFormat(\DateTime::ISO8601, $timestamp);
        if ($date === false) {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $timestamp); // ISO 8601 с таймзоной
        }
        if ($date === false) {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $timestamp); // UTC вариант
        }

        return $date !== false;
    }

    private function isValidLogLevel(string $level): bool
    {
        $validLevels = [
            'debug', 'info', 'notice', 'warning',
            'error', 'critical', 'alert', 'emergency'
        ];

        return in_array(strtolower($level), $validLevels, true);
    }
}
