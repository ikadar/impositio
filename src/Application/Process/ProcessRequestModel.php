<?php

namespace App\Application\Process;

/**
 * Request model for the /process endpoint.
 *
 * Contains validated parts with their actions.
 */
readonly class ProcessRequestModel
{
    /**
     * @param PartPayload[] $parts
     */
    public function __construct(
        public array $parts,
    ) {}
}
