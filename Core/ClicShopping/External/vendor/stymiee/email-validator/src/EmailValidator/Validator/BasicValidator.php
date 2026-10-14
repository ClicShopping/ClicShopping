<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;

/**
 * Validates the basic format of an email address
 *
 * This validator checks if the email address follows the basic format requirements
 * using PHP's built-in filter_var function with FILTER_VALIDATE_EMAIL.
 */
class BasicValidator extends AValidator
{
    /**
     * Validates the basic format of an email address
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the email address has a valid format, false otherwise
     */
    public function validate(EmailAddress $email): bool
    {
        return (bool) filter_var($email->getEmailAddress(), FILTER_VALIDATE_EMAIL);
    }
}
