<?php

namespace App\DTO;

use App\Entity\LogEntry;
use Symfony\Component\Validator\Constraints as Assert;

class LogBatchRequest
{
    #[Assert\NotBlank(message: 'Logs array cannot be empty')]
    #[Assert\Count(
        min: 1,
        max: 1000,
        minMessage: 'At least one log is required',
        maxMessage: 'Maximum 1000 logs per batch'
    )]
    #[Assert\Valid]
    public array $logs = [];

    public function __construct(array $data)
    {
        if (isset($data['logs']) && is_array($data['logs'])) {
            $this->logs = array_map(function($logData) {
                return new LogEntry($logData);
            }, $data['logs']);
        }
    }
}
