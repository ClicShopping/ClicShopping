<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Validation;

final class ValidationResult
{
    /** @var ValidationError[] */
    private array $errors = [];

    public function addError(ValidationError $error): void
    {
        $this->errors[] = $error;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return ValidationError[]
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
