<?php

namespace EmailValidator\Tests;

use EmailValidator\EmailAddress;
use EmailValidator\EmailValidator;
use EmailValidator\Policy;
use EmailValidator\Validator\BannedListValidator;
use EmailValidator\Validator\BasicValidator;
use EmailValidator\Validator\DisposableEmailValidator;
use EmailValidator\Validator\FreeEmailValidator;
use EmailValidator\Validator\MxValidator;
use EmailValidator\Validator\Rfc5322Validator;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class EmailValidatorTest extends TestCase
{
    public function validateDataProvider(): array
    {
        return [
            [EmailValidator::FAIL_BASIC, false, true, true, true, true, true],
            [EmailValidator::FAIL_RFC5322, true, false, true, true, true, true],
            [EmailValidator::FAIL_MX_RECORD, true, true, false, true, true, true],
            [EmailValidator::FAIL_BANNED_DOMAIN, true, true, true, false, true, true],
            [EmailValidator::FAIL_DISPOSABLE_DOMAIN, true, true, true, true, false, true],
            [EmailValidator::FAIL_FREE_PROVIDER, true, true, true, true, true, false],
            [EmailValidator::NO_ERROR, true, true, true, true, true, true],
        ];
    }

    /**
     * @dataProvider validateDataProvider
     * @param int $errCode
     * @param bool $basic
     * @param bool $rfc5322
     * @param bool $mx
     * @param bool $banned
     * @param bool $disposable
     * @param bool $free
     * @throws ReflectionException
     */
    public function testValidate(int $errCode, bool $basic, bool $rfc5322, bool $mx, bool $banned, bool $disposable, bool $free): void
    {
        $emailValidator = new EmailValidator();

        $basicValidator = $this->createMock(BasicValidator::class);
        $basicValidator->method('validate')
            ->willReturn($basic);
        $bValidator = new \ReflectionProperty($emailValidator, 'basicValidator');
        $bValidator->setAccessible(true);
        $bValidator->setValue($emailValidator, $basicValidator);

        $rfc5322Validator = $this->createMock(Rfc5322Validator::class);
        $rfc5322Validator->method('validate')
            ->willReturn($rfc5322);
        $rValidator = new \ReflectionProperty($emailValidator, 'rfc5322Validator');
        $rValidator->setAccessible(true);
        $rValidator->setValue($emailValidator, $rfc5322Validator);

        $mxValidator = $this->createMock(MxValidator::class);
        $mxValidator->method('validate')
            ->willReturn($mx);
        $mValidator = new \ReflectionProperty($emailValidator, 'mxValidator');
        $mValidator->setAccessible(true);
        $mValidator->setValue($emailValidator, $mxValidator);

        $bannedValidator = $this->createMock(BannedListValidator::class);
        $bannedValidator->method('validate')
            ->willReturn($banned);
        $bnValidator = new \ReflectionProperty($emailValidator, 'bannedListValidator');
        $bnValidator->setAccessible(true);
        $bnValidator->setValue($emailValidator, $bannedValidator);

        $disposableValidator = $this->createMock(DisposableEmailValidator::class);
        $disposableValidator->method('validate')
            ->willReturn($disposable);
        $dValidator = new \ReflectionProperty($emailValidator, 'disposableEmailValidator');
        $dValidator->setAccessible(true);
        $dValidator->setValue($emailValidator, $disposableValidator);

        $freeValidator = $this->createMock(FreeEmailValidator::class);
        $freeValidator->method('validate')
            ->willReturn($free);
        $fValidator = new \ReflectionProperty($emailValidator, 'freeEmailValidator');
        $fValidator->setAccessible(true);
        $fValidator->setValue($emailValidator, $freeValidator);

        $reason = new \ReflectionProperty($emailValidator, 'reason');
        $reason->setAccessible(true);
        $emailValidator->validate('user@example.com');

        self::assertEquals($errCode, $reason->getValue($emailValidator));
    }

    public function errorReasonDataProvider(): array
    {
        return [
            [EmailValidator::FAIL_BASIC, 'Invalid format'],
            [EmailValidator::FAIL_RFC5322, 'Does not comply with RFC 5322'],
            [EmailValidator::FAIL_MX_RECORD, 'Domain does not accept email'],
            [EmailValidator::FAIL_BANNED_DOMAIN, 'Domain is banned'],
            [EmailValidator::FAIL_DISPOSABLE_DOMAIN, 'Domain is used by disposable email providers'],
            [EmailValidator::FAIL_FREE_PROVIDER, 'Domain is used by free email providers'],
            [EmailValidator::NO_ERROR, ''],
        ];
    }

    /**
     * @dataProvider errorReasonDataProvider
     * @param int $errorCode
     * @param string $errorMsg
     */
    public function testGetErrorReason(int $errorCode, string $errorMsg): void
    {
        $emailValidator = new EmailValidator();

        $reason = new \ReflectionProperty(EmailValidator::class, 'reason');
        $reason->setAccessible(true);
        $reason->setValue($emailValidator, $errorCode);

        self::assertEquals($errorMsg, $emailValidator->getErrorReason());
    }

    public function testGetErrorCode(): void
    {
        self::assertEquals(EmailValidator::NO_ERROR, (new EmailValidator())->getErrorCode());
    }
}
