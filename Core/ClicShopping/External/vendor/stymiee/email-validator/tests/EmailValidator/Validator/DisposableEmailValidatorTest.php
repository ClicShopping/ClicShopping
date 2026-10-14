<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\Validator\DisposableEmailValidator;
use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class DisposableEmailValidatorTest extends TestCase
{
    public function testValidateExplicit(): void
    {
        $policy = [
            'checkDisposableEmail' => false
        ];
        $validator = new DisposableEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateDefault(): void
    {
        $validator = new DisposableEmailValidator(new Policy());
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateClientProvidedDomain(): void
    {
        $policy = [
            'checkDisposableEmail' => true,
            'disposableList' => [
                'example.com'
            ],
        ];
        $validator = new DisposableEmailValidator(new Policy($policy));
        self::assertFalse($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateInvalidEmail(): void
    {
        $policy = [
            'checkDisposableEmail' => true,
            'disposableList' => [
                'example.com'
            ],
        ];
        $validator = new DisposableEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('invalid-email')));
    }

    public function testValidateEmptyDomain(): void
    {
        $policy = [
            'checkDisposableEmail' => true,
            'disposableList' => [
                'example.com'
            ],
        ];
        $validator = new DisposableEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@')));
    }

    public function testValidateWithEmptyList(): void
    {
        $policy = [
            'checkDisposableEmail' => true,
            'disposableList' => [],
        ];
        $validator = new DisposableEmailValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }
}
