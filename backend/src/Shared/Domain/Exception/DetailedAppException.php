<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class DetailedAppException extends AppException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        /** @var array<string, mixed> */
        private readonly array $details = [],
        private readonly ?string $field = null,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->details,
            'field' => $this->field,
        ];
    }
}
