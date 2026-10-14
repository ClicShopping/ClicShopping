<?php

declare(strict_types=1);

namespace Tests\EmailValidator\Validator\LocalPart;

use EmailValidator\Validator\LocalPart\QuotedStringValidator;
use PHPUnit\Framework\TestCase;

class QuotedStringValidatorTest extends TestCase
{
    private QuotedStringValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new QuotedStringValidator();
    }

    /**
     * @dataProvider validQuotedStringsProvider
     */
    public function testValidQuotedStrings(string $quotedString): void
    {
        $this->assertTrue($this->validator->validate($quotedString));
    }

    /**
     * @dataProvider invalidQuotedStringsProvider
     */
    public function testInvalidQuotedStrings(string $quotedString): void
    {
        $this->assertFalse($this->validator->validate($quotedString));
    }

    public function validQuotedStringsProvider(): array
    {
        return [
            'empty quoted string' => ['""'],
            'simple text' => ['"simple"'],
            'with spaces' => ['"user name"'],
            'with dots' => ['"user.name"'],
            'with special chars' => ['"!#$%&\'*+-/=?^_`{|}~"'],
            'escaped quote' => ['"user\"name"'],
            'escaped backslash' => ['"user\\\\name"'],
            'multiple escaped chars' => [<<<'EOD'
"user\"\\name"
EOD],
            'with at symbol' => ['"user@name"'],
            'with brackets' => ['"user[name]"'],
            'with parentheses' => ['"user(name)"'],
            'with angle brackets' => ['"user<n>"'],
            'with curly braces' => ['"user{name}"'],
            'with semicolon' => ['"user;name"'],
            'with comma' => ['"user,name"'],
            'with tab' => ["\"user\tname\""],
            'complex mix' => ['"user.\"name\"@[domain]"'],
        ];
    }

    public function invalidQuotedStringsProvider(): array
    {
        return [
            'empty string' => [''],
            'unquoted string' => ['simple'],
            'missing start quote' => ['simple"'],
            'missing end quote' => ['"simple'],
            'unescaped quote' => ['"user"name"'],
            'lone backslash at end' => ['"user\\"'],
            'invalid escape sequence' => ['"user\name"'],
            'non-printable char' => ["\"\x01\""],
            'high ascii char' => ["\"\x80\""],
            'newline' => ["\"user\nname\""],
            'carriage return' => ["\"user\rname\""],
            'null byte' => ["\"user\0name\""],
        ];
    }
} 