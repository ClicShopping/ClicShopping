<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\Validator\BasicValidator;
use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class BasicValidatorTest extends TestCase
{
    public function dataProvider(): array
    {
        return [
            ['user@example.com', true],
            ['user@example', false],
            ['@example.com', false],
        ];
    }

    /**
     * @dataProvider dataProvider
     * @param string $email
     * @param bool $valid
     */
    public function testValidate(string $email, bool $valid): void
    {
        $isValid = (new BasicValidator(new Policy()))->validate(new EmailAddress($email));
        self::assertEquals($valid, $isValid);
    }
}
