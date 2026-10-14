<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\Validator\GmailValidator;
use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the GmailValidator class
 * 
 * @since 2.0.0
 */
class GmailValidatorTest extends TestCase
{
    private GmailValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new GmailValidator(new Policy());
    }

    /**
     * @dataProvider validateProvider
     * @since 2.0.0
     */
    public function testValidate(string $email, bool $expected): void
    {
        $this->assertEquals($expected, $this->validator->validate(new EmailAddress($email)));
    }

    /**
     * @since 2.0.0
     */
    public function validateProvider(): array
    {
        return [
            ['test@gmail.com', true],
            ['test@googlemail.com', true],
            ['test@example.com', false],
            ['test+alias@gmail.com', true],
            ['test.alias@gmail.com', true],
        ];
    }

    /**
     * @dataProvider isGmailWithPlusCharProvider
     * @since 2.0.0
     */
    public function testIsGmailWithPlusChar(string $email, bool $expected): void
    {
        $this->assertEquals($expected, $this->validator->isGmailWithPlusChar(new EmailAddress($email)));
    }

    /**
     * @since 2.0.0
     */
    public function isGmailWithPlusCharProvider(): array
    {
        return [
            ['test@gmail.com', false],
            ['test+alias@gmail.com', true],
            ['test+alias@googlemail.com', true],
            ['test@example.com', false],
            ['test+alias@example.com', false],
        ];
    }

    /**
     * @dataProvider getGmailAddressWithoutPlusProvider
     * @since 2.0.0
     */
    public function testGetGmailAddressWithoutPlus(string $email, string $expected): void
    {
        $this->assertEquals($expected, $this->validator->getGmailAddressWithoutPlus(new EmailAddress($email)));
    }

    /**
     * @since 2.0.0
     */
    public function getGmailAddressWithoutPlusProvider(): array
    {
        return [
            ['test@gmail.com', 'test@gmail.com'],
            ['test+alias@gmail.com', 'test@gmail.com'],
            ['test+alias@googlemail.com', 'test@googlemail.com'],
            ['test@example.com', 'test@example.com'],
            ['test+alias@example.com', 'test+alias@example.com'],
        ];
    }

    /**
     * @dataProvider getSanitizedGmailAddressProvider
     * @since 2.0.0
     */
    public function testGetSanitizedGmailAddress(string $email, string $expected): void
    {
        $this->assertEquals($expected, $this->validator->getSanitizedGmailAddress(new EmailAddress($email)));
    }

    /**
     * @since 2.0.0
     */
    public function getSanitizedGmailAddressProvider(): array
    {
        return [
            ['test@gmail.com', 'test@gmail.com'],
            ['test+alias@gmail.com', 'test@gmail.com'],
            ['test.alias@gmail.com', 'testalias@gmail.com'],
            ['test+alias@googlemail.com', 'test@googlemail.com'],
            ['test.alias+alias@gmail.com', 'testalias@gmail.com'],
            ['test@example.com', 'test@example.com'],
            ['test+alias@example.com', 'test+alias@example.com'],
        ];
    }
} 