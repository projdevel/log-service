<?php

namespace App\Service;

/**
 * Service for generate the batch
 */
class BatchIdGenerator
{
    public function generate(): string
    {
        return sprintf(
            'batch_%s_%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }
}
