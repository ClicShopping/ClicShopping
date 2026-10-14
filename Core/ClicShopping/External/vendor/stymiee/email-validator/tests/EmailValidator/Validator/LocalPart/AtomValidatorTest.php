<?php

declare(strict_types=1);

namespace Tests\EmailValidator\Validator\LocalPart;

use EmailValidator\Validator\LocalPart\AtomValidator;
use PHPUnit\Framework\TestCase;

class AtomValidatorTest extends TestCase
{
    private AtomValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AtomValidator();
    }

    /**
     * @dataProvider validLocalPartsProvider
     */
    public function testValidLocalParts(string $localPart): void
    {
        $this->assertTrue($this->validator->validate($localPart));
    }

    /**
     * @dataProvider invalidLocalPartsProvider
     */
    public function testInvalidLocalParts(string $localPart): void
    {
        $this->assertFalse($this->validator->validate($localPart));
    }

    public function validLocalPartsProvider(): array
    {
        return [
            'simple' => ['simple'],
            'alphanumeric' => ['user123'],
            'with allowed special chars' => ['user.name'],
            'multiple dots' => ['first.middle.last'],
            'all special chars' => ['!#$%&\'*+-/=?^_`{|}~'],
            'mixed case' => ['First.Last'],
            'numbers and special chars' => ['user+123'],
            'underscore' => ['user_name'],
            'complex mix' => ['first.last+tag'],
        ];
    }

    public function invalidLocalPartsProvider(): array
    {
        return [
            'empty string' => [''],
            'single dot' => ['.'],
            'starts with dot' => ['.user'],
            'ends with dot' => ['user.'],
            'consecutive dots' => ['user..name'],
            'space' => ['user name'],
            'tab' => ["user\tname"],
            'newline' => ["user\nname"],
            'invalid chars' => ['user@name'],
            'brackets' => ['user[name]'],
            'quotes' => ['user"name'],
            'backslash' => ['user\\name'],
            'comma' => ['user,name'],
            'semicolon' => ['user;name'],
            'greater than' => ['user>name'],
            'less than' => ['user<name'],
        ];
    }
} 