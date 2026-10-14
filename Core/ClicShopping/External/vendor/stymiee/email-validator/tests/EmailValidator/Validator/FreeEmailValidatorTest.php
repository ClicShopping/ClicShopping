<?php

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class FreeEmailValidatorTest extends TestCase
{
    public function testValidateExplicit(): void
    {
        $policy = [
            'checkFreeEmail' => false
        ];
        $validator = new FreeEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateDefault(): void
    {
        $validator = new FreeEmailValidator(new Policy());
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateClientProvidedDomain(): void
    {
        $policy = [
            'checkFreeEmail' => true,
            'freeList' => [
                'example.com'
            ],
        ];
        $validator = new FreeEmailValidator(new Policy($policy));
        self::assertFalse($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateInvalidEmail(): void
    {
        $policy = [
            'checkFreeEmail' => true,
            'freeList' => [
                'example.com'
            ],
        ];
        $validator = new FreeEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('invalid-email')));
    }

    public function testValidateEmptyDomain(): void
    {
        $policy = [
            'checkFreeEmail' => true,
            'freeList' => [
                'example.com'
            ],
        ];
        $validator = new FreeEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@')));
    }

    public function testValidateWithEmptyList(): void
    {
        $policy = [
            'checkFreeEmail' => true,
            'freeList' => [],
        ];
        $validator = new FreeEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }
}
