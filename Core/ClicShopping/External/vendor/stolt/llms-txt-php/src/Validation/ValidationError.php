<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Validation;

final class ValidationError
{
    public function __construct(
        private readonly string $message
    ) {
    }

    public function message(): string
    {
        return $this->message;
    }
}
