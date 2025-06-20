<?php

namespace App\Application\Joblang\UseCase\ParseJoblangScript;

use App\Domain\Job\Job;

class JoblangScriptParseResponseModel
{
    public function __construct(
        public readonly Job $job,
        public readonly array $parts,
        public readonly array $metaData
    ) {

    }
}