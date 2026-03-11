<?php

namespace App\Service;

use App\DTO\LogBatchRequest;
use App\Exception\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validator for logs
 */
class LogValidator
{
    public function __construct(protected readonly ValidatorInterface $validator)
    {}

    public function validate(LogBatchRequest $request): void
    {
        $errors = [];

        if (empty($request->logs)) {
            $errors[] = 'Logs array cannot be empty';
        }

        if (count($request->logs) > 1000) {
            $errors[] = 'Maximum 1000 logs per batch';
        }

        foreach ($request->logs as $index => $log) {
            if (empty($log->timestamp)) {
                $errors[] = "Log #$index: timestamp is required";
            }

            if (empty($log->level)) {
                $errors[] = "Log #$index: level is required";
            }

            if (empty($log->service)) {
                $errors[] = "Log #$index: service is required";
            }

            if (empty($log->message)) {
                $errors[] = "Log #$index: message is required";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException(
                'Validation failed: ' . implode(', ', $errors),
                $errors
            );
        }
    }
}
