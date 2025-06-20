<?php

namespace App\Application\Joblang\UseCase\ParseJoblangScript;

class JoblangScriptParseRequestModel
{
    public function __construct(
        public readonly string $scriptContent
    ) {}
}