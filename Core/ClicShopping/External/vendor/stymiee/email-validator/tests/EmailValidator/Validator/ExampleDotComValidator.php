<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\EmailAddress;
use EmailValidator\Validator\AValidator;

/**
 * Example custom validator that only allows example.com domains
 */
class ExampleDotComValidator extends AValidator
{
    public function validate(EmailAddress $email): bool
    {
        return $email->getDomain() === 'example.com';
    }
}
