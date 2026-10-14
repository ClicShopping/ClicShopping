<?php

declare(strict_types=1);

namespace Tests\EmailValidator\Validator\Domain;

use EmailValidator\Validator\Domain\DomainLiteralValidator;
use PHPUnit\Framework\TestCase;

class DomainLiteralValidatorTest extends TestCase
{
    private DomainLiteralValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DomainLiteralValidator();
    }

    /**
     * @dataProvider validDomainLiteralsProvider
     */
    public function testValidDomainLiterals(string $domain): void
    {
        $this->assertTrue($this->validator->validate($domain));
    }

    /**
     * @dataProvider invalidDomainLiteralsProvider
     */
    public function testInvalidDomainLiterals(string $domain): void
    {
        $this->assertFalse($this->validator->validate($domain));
    }

    public function validDomainLiteralsProvider(): array
    {
        return [
            'simple IPv4' => ['[192.168.1.1]'],
            'IPv4 with leading zeros' => ['[192.168.001.001]'],
            'IPv4 max values' => ['[255.255.255.255]'],
            'IPv4 min values' => ['[0.0.0.0]'],
            'simple IPv6' => ['[IPv6:2001:db8::1]'],
            'full IPv6' => ['[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370:7334]'],
            'IPv6 uppercase' => ['[IPv6:2001:DB8::1]'],
            'IPv6 mixed case' => ['[IPv6:2001:dB8::1]'],
            'IPv6 with zeros' => ['[IPv6:0:0:0:0:0:0:0:1]'],
            'IPv6 localhost' => ['[IPv6:0:0:0:0:0:0:0:1]'],
            'IPv6 all max' => ['[IPv6:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:FFFF]'],
        ];
    }

    public function invalidDomainLiteralsProvider(): array
    {
        return [
            'empty string' => [''],
            'missing brackets' => ['192.168.1.1'],
            'missing start bracket' => ['192.168.1.1]'],
            'missing end bracket' => ['[192.168.1.1'],
            'empty brackets' => ['[]'],
            'IPv4 empty octet' => ['[192.168..1]'],
            'IPv4 too few octets' => ['[192.168.1]'],
            'IPv4 too many octets' => ['[192.168.1.1.1]'],
            'IPv4 invalid octet' => ['[192.168.1.256]'],
            'IPv4 negative octet' => ['[192.168.1.-1]'],
            'IPv4 non-numeric' => ['[192.168.1.abc]'],
            'IPv6 missing prefix' => ['[2001:db8::1]'],
            'IPv6 wrong prefix' => ['[IPV6:2001:db8::1]'],
            'IPv6 too few groups' => ['[IPv6:2001:db8]'],
            'IPv6 too many groups' => ['[IPv6:2001:db8:1:2:3:4:5:6:7]'],
            'IPv6 invalid chars' => ['[IPv6:2001:db8::g]'],
            'IPv6 group too long' => ['[IPv6:20011:db8::1]'],
            'mixed content' => ['[text and numbers]'],
            'with spaces' => ['[192.168.1.1 ]'],
            'with newline' => ["[192.168.1.1\n]"],
            'with tab' => ["[192.168.1.1\t]"],
        ];
    }
} 