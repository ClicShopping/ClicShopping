<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;
use EmailValidator\Policy;

/**
 * Abstract base class for email validators
 *
 * This abstract class provides common functionality for all email validators.
 * It implements the IValidator interface and provides access to the validation policy.
 */
abstract class AValidator implements IValidator
{
    /**
     * The validation policy containing configuration and rules
     *
     * @var Policy
     */
    protected Policy $policy;

    /**
     * Constructor for the validator
     *
     * @param Policy $policy The validation policy to use
     */
    public function __construct(Policy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Validates an email address according to specific rules
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the email address passes validation, false otherwise
     */
    abstract public function validate(EmailAddress $email): bool;
}
