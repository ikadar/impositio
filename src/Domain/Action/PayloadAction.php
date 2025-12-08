<?php

namespace App\Domain\Action;

/**
 * DTO representing an action from the /process endpoint JSON payload.
 *
 * This is a simple data transfer object that holds the action name and its parameters
 * as received from the client. Parameter validation is not implemented yet.
 */
readonly class PayloadAction
{
    public function __construct(
        public ActionName $name,
        public array $params = [],
    ) {}

    /**
     * Create a PayloadAction from a raw array (JSON payload).
     *
     * @param array $data Raw action data with 'name' and optional 'params' keys
     * @return self
     * @throws \InvalidArgumentException If 'name' is missing or invalid
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name'])) {
            throw new \InvalidArgumentException("Action 'name' is required");
        }

        $actionName = ActionName::tryFrom($data['name']);
        if ($actionName === null) {
            throw new \InvalidArgumentException("Unknown action name: {$data['name']}");
        }

        return new self(
            name: $actionName,
            params: $data['params'] ?? [],
        );
    }

    /**
     * Get the corresponding MachineType for this action.
     */
    public function getMachineType(): \App\Domain\Equipment\MachineType
    {
        return $this->name->machineType();
    }

    /**
     * Convert back to array format.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name->value,
            'params' => $this->params,
        ];
    }
}
