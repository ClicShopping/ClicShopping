<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\Validator\BannedListValidator;
use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class BannedListValidatorTest extends TestCase
{
    public function dataProvider(): array
    {
        $bannedList = [
            'example.com',
            '*.example.com',
        ];

        return [
            [$bannedList, 'user@example.com', true, false],
            [$bannedList, 'user@gmail.com'  , true, true],
            [$bannedList, 'user@example.com', false, true],
            [$bannedList, 'user@gmail.com'  , false, true],
            [$bannedList, 'user@test.example.com', true, false],
            [$bannedList, 'user@', true, true],
        ];
    }

    /**
     * @dataProvider dataProvider
     * @param array $bannedList
     * @param bool $enabled
     * @param string $email
     * @param bool $valid
     */
    public function testValidate(array $bannedList, string $email, bool $enabled, bool $valid): void
    {
        $policy = [
            'checkBannedListedEmail' => $enabled,
            'bannedList' => $bannedList
        ];
        $validator = new BannedListValidator(new Policy($policy));
        self::assertEquals($valid, $validator->validate(new EmailAddress($email)));
    }
}
