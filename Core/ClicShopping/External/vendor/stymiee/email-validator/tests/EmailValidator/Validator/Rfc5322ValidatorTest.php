<?php

declare(strict_types=1);

namespace Tests\EmailValidator\Validator;

use EmailValidator\EmailAddress;
use EmailValidator\Validator\Rfc5322Validator;
use PHPUnit\Framework\TestCase;

class Rfc5322ValidatorTest extends TestCase
{
    private Rfc5322Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Rfc5322Validator();
    }

    /**
     * @dataProvider validEmailsProvider
     */
    public function testValidEmails(string $email): void
    {
        $emailObj = new EmailAddress($email);
        $this->assertTrue($this->validator->validate($emailObj));
    }

    /**
     * @dataProvider invalidEmailsProvider
     */
    public function testInvalidEmails(string $email): void
    {
        $emailObj = new EmailAddress($email);
        $this->assertFalse($this->validator->validate($emailObj));
    }

    public function validEmailsProvider(): array
    {
        return [
            // Regular email addresses
            'simple' => ['user@example.com'],
            'with numbers' => ['user123@example.com'],
            'with special chars' => ['user+tag@example.com'],
            'with dots' => ['first.last@example.com'],
            'subdomain' => ['user@sub.example.com'],

            // Local part variations
            'quoted simple' => ['"user"@example.com'],
            'quoted with spaces' => ['"John Doe"@example.com'],
            'quoted with special chars' => ['"user@name"@example.com'],
            'quoted with escaped chars' => ['"user\"name"@example.com'],
            'complex local part' => ['first.middle.last+tag@example.com'],

            // Domain variations
            'IPv4 domain' => ['user@[192.168.1.1]'],
            'IPv4 with zeros' => ['user@[192.168.001.001]'],
            'IPv6 domain' => ['user@[IPv6:2001:db8::1]'],
            'multiple subdomains' => ['user@a.b.c.example.com'],
            'domain with hyphen' => ['user@my-domain.example.com'],

            // Complex combinations
            'complex quoted with IPv4' => ['"user.name"@[192.168.1.1]'],
            'complex quoted with IPv6' => ['"user.name"@[IPv6:2001:db8::1]'],
            'all special chars local' => ['!#$%&\'*+-/=?^_`{|}~@example.com'],
        ];
    }

    public function invalidEmailsProvider(): array
    {
        return [
            // Basic validation
            'empty string' => [''],
            'no at symbol' => ['userexample.com'],
            'multiple at symbols' => ['user@domain@example.com'],
            'empty local part' => ['@example.com'],
            'empty domain' => ['user@'],

            // Local part validation
            'local part too long' => [str_repeat('a', 65) . '@example.com'],
            'unescaped quote' => ['"user"name"@example.com'],
            'invalid chars in local' => ['user name@example.com'],
            'dot at start' => ['.user@example.com'],
            'dot at end' => ['user.@example.com'],
            'consecutive dots' => ['user..name@example.com'],

            // Domain validation
            'single label domain' => ['user@localhost'],
            'invalid domain chars' => ['user@example_domain.com'],
            'domain starts with dot' => ['user@.example.com'],
            'domain ends with dot' => ['user@example.com.'],
            'consecutive dots in domain' => ['user@example..com'],
            'domain starts with hyphen' => ['user@-example.com'],
            'domain ends with hyphen' => ['user@example-.com'],
            'domain too long' => ['user@' . str_repeat('a.', 127) . 'com'],

            // Domain literal validation
            'invalid IPv4' => ['user@[192.168.1.256]'],
            'incomplete IPv4' => ['user@[192.168.1]'],
            'invalid IPv6 prefix' => ['user@[IPV6:2001:db8::1]'],
            'invalid IPv6 groups' => ['user@[IPv6:2001:db8]'],
            'invalid brackets' => ['user@192.168.1.1]'],

            // Special cases
            'with line break' => ["user@example\n.com"],
            'with carriage return' => ["user@example\r.com"],
            'with tab' => ["user@example\t.com"],
            'with null byte' => ["user@example\0.com"],
        ];
    }
}
