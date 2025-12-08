<?php

namespace App\Service;

use App\Domain\Action\PayloadAction;

interface ActionValidatorInterface
{
    /**
     * Validate an array of raw action data from the JSON payload.
     *
     * @param array $actionsData Raw actions array from payload
     * @return ActionValidationResult
     */
    public function validate(array $actionsData): ActionValidationResult;
}
