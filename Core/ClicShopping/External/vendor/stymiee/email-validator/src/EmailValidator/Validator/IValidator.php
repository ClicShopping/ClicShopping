<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;

/**
 * Interface for email validators
 *
 * This interface defines the contract that all email validators must implement.
 * Validators are responsible for checking specific aspects of an email address
 * and determining if it meets certain criteria.
 */
interface IValidator
{
    /**
     * Validates an email address according to specific rules
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the email address passes validation, false otherwise
     */
    public function validate(EmailAddress $email): bool;
}
