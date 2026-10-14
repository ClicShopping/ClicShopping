<?php

declare(strict_types=1);

namespace Tests\EmailValidator\Validator\Domain;

use EmailValidator\Validator\Domain\DomainNameValidator;
use PHPUnit\Framework\TestCase;

class DomainNameValidatorTest extends TestCase
{
    private DomainNameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DomainNameValidator();
    }

    /**
     * @dataProvider validDomainsProvider
     */
    public function testValidDomains(string $domain): void
    {
        $this->assertTrue($this->validator->validate($domain));
    }

    /**
     * @dataProvider invalidDomainsProvider
     */
    public function testInvalidDomains(string $domain): void
    {
        $this->assertFalse($this->validator->validate($domain));
    }

    public function validDomainsProvider(): array
    {
        return [
            'simple domain' => ['example.com'],
            'subdomain' => ['sub.example.com'],
            'multiple subdomains' => ['a.b.c.example.com'],
            'numeric domain' => ['123.example.com'],
            'alphanumeric' => ['test123.example.com'],
            'with hyphen' => ['my-domain.example.com'],
            'hyphen in multiple parts' => ['my-domain.my-example.com'],
            'single character parts' => ['a.b.c.d'],
            'max length label' => [str_repeat('a', 63) . '.com'],
            'idn domain' => ['xn--80akhbyknj4f.com'], // IDN example
            'complex mix' => ['sub-123.example-domain.com'],
        ];
    }

    public function invalidDomainsProvider(): array
    {
        return [
            'empty string' => [''],
            'single label' => ['localhost'],
            'starts with dot' => ['.example.com'],
            'ends with dot' => ['example.com.'],
            'consecutive dots' => ['example..com'],
            'starts with hyphen' => ['-example.com'],
            'ends with hyphen' => ['example-.com'],
            'consecutive hyphens' => ['ex--ample.com'],
            'too long label' => [str_repeat('a', 64) . '.com'],
            'too long domain' => [str_repeat('a.', 127) . 'com'], // Over 255 chars
            'invalid chars' => ['example_domain.com'],
            'with space' => ['example domain.com'],
            'with tab' => ["example\tdomain.com"],
            'with newline' => ["example\ndomain.com"],
            'with @' => ['example@domain.com'],
            'with brackets' => ['example[1].com'],
            'with parentheses' => ['example(1).com'],
            'with special chars' => ['example!.com'],
        ];
    }
} 