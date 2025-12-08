<?php

namespace App\Service;

use App\Domain\Action\PayloadAction;

/**
 * Result of action validation.
 *
 * Contains either validated PayloadAction objects (on success)
 * or structured error information (on failure).
 */
readonly class ActionValidationResult
{
    private function __construct(
        public bool $isValid,
        /** @var PayloadAction[] */
        public array $actions,
        public ?string $errorMessage,
        public ?string $errorCode,
    ) {}

    /**
     * Create a successful validation result.
     *
     * @param PayloadAction[] $actions
     */
    public static function success(array $actions): self
    {
        return new self(
            isValid: true,
            actions: $actions,
            errorMessage: null,
            errorCode: null,
        );
    }

    /**
     * Create a failed validation result.
     */
    public static function failure(string $message, string $code): self
    {
        return new self(
            isValid: false,
            actions: [],
            errorMessage: $message,
            errorCode: $code,
        );
    }

    /**
     * Get error response array for JSON output.
     */
    public function getErrorResponse(): array
    {
        return [
            'error' => $this->errorMessage,
            'code' => $this->errorCode,
        ];
    }
}
