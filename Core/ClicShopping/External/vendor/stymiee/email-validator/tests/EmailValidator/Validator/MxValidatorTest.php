<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\Validator\MxValidator;
use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class MxValidatorTest extends TestCase
{
    public function testValidate(): void
    {
        $policy = [
            'checkMxRecords' => false
        ];
        $validator = new MxValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('user@example.com')));
    }

    public function testValidateDns(): void
    {
        $policy = [
            'checkMxRecords' => true
        ];
        $validator = new MxValidator(new Policy($policy));
        self::assertTrue($validator->validate(new EmailAddress('stymiee@gmail.com')));
    }
}
