<?php

namespace App\Service;

use App\Domain\Action\ActionName;
use App\Domain\Action\PayloadAction;

class ActionValidator implements ActionValidatorInterface
{
    /**
     * Validate an array of raw action data from the JSON payload.
     *
     * @param array $actionsData Raw actions array from payload
     * @return ActionValidationResult
     */
    public function validate(array $actionsData): ActionValidationResult
    {
        $validatedActions = [];

        foreach ($actionsData as $index => $actionData) {
            // Check if action data is an array
            if (!is_array($actionData)) {
                return ActionValidationResult::failure(
                    "Action at index {$index} must be an object",
                    'INVALID_ACTION_FORMAT'
                );
            }

            // Check if 'name' field exists
            if (!isset($actionData['name'])) {
                return ActionValidationResult::failure(
                    "Action at index {$index} is missing required 'name' field",
                    'MISSING_ACTION_NAME'
                );
            }

            // Check if 'name' is a string
            if (!is_string($actionData['name'])) {
                return ActionValidationResult::failure(
                    "Action 'name' at index {$index} must be a string",
                    'INVALID_ACTION_NAME_TYPE'
                );
            }

            // Validate action name against enum
            $actionName = ActionName::tryFrom($actionData['name']);
            if ($actionName === null) {
                $allowedNames = implode(', ', array_map(fn($case) => $case->value, ActionName::cases()));
                return ActionValidationResult::failure(
                    "Unknown action name: {$actionData['name']}. Allowed values: {$allowedNames}",
                    'INVALID_ACTION_NAME'
                );
            }

            // Create PayloadAction (params validation is skipped for now)
            $validatedActions[] = new PayloadAction(
                name: $actionName,
                params: $actionData['params'] ?? [],
            );
        }

        return ActionValidationResult::success($validatedActions);
    }
}
