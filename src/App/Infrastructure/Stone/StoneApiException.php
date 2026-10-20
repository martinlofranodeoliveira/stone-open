<?php
declare(strict_types=1);

namespace App\Infrastructure\Stone;

final class StoneApiException extends \RuntimeException
{
    private int $status;
    private string $errorCode;
    private string $errorMessage;
    private ?array $errors;
    private ?array $raw;

    public function __construct(int $status, string $errorCode, string $errorMessage, ?array $errors = null, ?array $raw = null)
    {
        parent::__construct($errorCode . ': ' . $errorMessage, $status);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->errors = $errors;
        $this->raw = $raw;
    }

    public function getStatus(): int { return $this->status; }
    public function getErrorCode(): string { return $this->errorCode; }
    public function getErrorMessage(): string { return $this->errorMessage; }
    public function getErrors(): ?array { return $this->errors; }
    public function getRaw(): ?array { return $this->raw; }
}

