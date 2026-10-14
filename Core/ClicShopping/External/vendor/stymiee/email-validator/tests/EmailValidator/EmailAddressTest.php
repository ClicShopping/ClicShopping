<?php

namespace EmailValidator\Tests;

use EmailValidator\EmailAddress;
use PHPUnit\Framework\TestCase;

class EmailAddressTest extends TestCase
{
    public function domainDataProvider(): array
    {
        return [
            ['user@example.com', 'example.com'],
            ['example.com', null],
        ];
    }

    /**
     * @dataProvider domainDataProvider
     * @param string $emailAddress
     * @param string|null $domain
     */
    public function testGetDomain(string $emailAddress, ?string $domain): void
    {
        $email = new EmailAddress($emailAddress);
        self::assertEquals($email->getDomain(), $domain);
    }

    public function emailDataProvider(): array
    {
        return [
            ['test@johnconde.net'],
            ['test@gmail.com'],
            ['test@hotmail.com'],
            ['test@outlook.com'],
            ['test@yahoo.com'],
            ['test@domain.com'],
            ['test@example.com'],
            ['test@example2.com'],
            ['test@nobugmail.com'],
            ['test@mxfuel.com'],
            ['test@cellurl.com'],
            ['test@10minutemail.com'],
        ];
    }

    /**
     * @dataProvider emailDataProvider
     * @param string $emailAddress
     */
    public function testGetEmailAddress(string $emailAddress): void
    {
        $email = new EmailAddress($emailAddress);
        self::assertEquals($email->getEmailAddress(), $emailAddress);
    }

    public function emailWithPlusDataProvider(): array
    {
        return [
            ['test@gmail.com', false],
            ['test+example@gmail.com', true],
            ['test@example.com', false],
            ['test+example@example.com', false],
            ['@example.com', false],
        ];
    }

    /**
     * @dataProvider emailWithPlusDataProvider
     * @param string $emailAddress
     * @param bool $hasPlus
     */
    public function testIsGmailWithPlusChar(string $emailAddress, bool $hasPlus): void
    {
        $email = new EmailAddress($emailAddress);
        self::assertEquals($hasPlus, $email->isGmailWithPlusChar());
    }

    public function testGetGmailAddressWithoutPlus(): void
    {
        $email = new EmailAddress('user+test@example.com');
        self::assertEquals('user@example.com', $email->getGmailAddressWithoutPlus());
    }

    public function gmailAddressDataProvider(): array
    {
        return [
            ['example.com@gmail.com' , 'examplecom@gmail.com'],
            ['example.com@googlemail.com' , 'examplecom@googlemail.com'],
            ['e.x.a.m.p.l.e.c.o.m@gmail.com' , 'examplecom@gmail.com'],
            ['e.x.a.m.p.l.e.c.o.m+string@gmail.com' , 'examplecom@gmail.com'],
            ['example.com+string@gmail.com' , 'examplecom@gmail.com'],
        ];
    }

    /**
     * @dataProvider gmailAddressDataProvider
     * @param string $emailAddress
     * @param string $resultAddress
     */
    public function testGetSanitizedGmailAddress(string $emailAddress, string $resultAddress): void
    {
        $email = new EmailAddress($emailAddress);
        self::assertEquals($email->getSanitizedGmailAddress(), $resultAddress);
    }
}
