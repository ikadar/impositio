<?php

namespace App\Application\Process;

use App\Domain\Action\PayloadAction;

/**
 * DTO representing a part from the /process endpoint JSON payload.
 */
readonly class PartPayload
{
    /**
     * @param string $partId
     * @param PayloadAction[] $actions
     * @param array $properties
     * @param array $requiredParts
     */
    public function __construct(
        public string $partId,
        public array $actions,
        public array $properties = [],
        public array $requiredParts = [],
    ) {}

    /**
     * Create a PartPayload from a raw array (JSON payload).
     *
     * @param array $data Raw part data
     * @param PayloadAction[] $validatedActions Pre-validated actions
     * @return self
     */
    public static function fromArray(array $data, array $validatedActions): self
    {
        return new self(
            partId: $data['partId'] ?? '',
            actions: $validatedActions,
            properties: $data['properties'] ?? [],
            requiredParts: $data['required_parts'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'partId' => $this->partId,
            'actions' => array_map(fn(PayloadAction $a) => $a->toArray(), $this->actions),
            'properties' => $this->properties,
            'required_parts' => $this->requiredParts,
        ];
    }
}
