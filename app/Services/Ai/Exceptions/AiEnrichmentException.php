<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

class AiEnrichmentException extends RuntimeException
{
    /**
     * @param  array{
     *     error_code?: int|null,
     *     raw_response?: string|null,
     *     error_reason?: string,
     *     suggested_action?: string,
     *     message?: string,
     * }  $errorDetails
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        protected array $errorDetails = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  array{
     *     error_code?: int|null,
     *     raw_response?: string|null,
     *     error_reason?: string,
     *     suggested_action?: string,
     *     message?: string,
     * }  $details
     */
    public static function fromInterpreted(array $details): self
    {
        return new self(
            message: (string) ($details['message'] ?? $details['error_reason'] ?? 'AI enrichment failed.'),
            errorDetails: $details,
        );
    }

    /**
     * @return array{
     *     error_code?: int|null,
     *     raw_response?: string|null,
     *     error_reason?: string,
     *     suggested_action?: string,
     *     message?: string,
     * }
     */
    public function errorDetails(): array
    {
        return $this->errorDetails;
    }

    public function hasErrorDetails(): bool
    {
        return $this->errorDetails !== [];
    }
}
