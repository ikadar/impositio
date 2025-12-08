<?php

namespace App\Application\Process;

/**
 * Response model for the /process endpoint.
 */
readonly class ProcessResponseModel
{
    public function __construct(
        public int|string $id,
    ) {}
}
