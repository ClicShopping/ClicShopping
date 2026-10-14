<?php

declare(strict_types=1);

namespace EmailValidator;

use EmailValidator\Validator\AValidator;
use EmailValidator\Validator\BannedListValidator;
use EmailValidator\Validator\BasicValidator;
use EmailValidator\Validator\DisposableEmailValidator;
use EmailValidator\Validator\FreeEmailValidator;
use EmailValidator\Validator\GmailValidator;
use EmailValidator\Validator\MxValidator;
use EmailValidator\Validator\Rfc5322Validator;

class EmailValidator
{
    public const NO_ERROR = 0;

    public const FAIL_BASIC = 1;

    public const FAIL_MX_RECORD = 2;

    public const FAIL_BANNED_DOMAIN = 3;

    public const FAIL_DISPOSABLE_DOMAIN = 4;

    public const FAIL_FREE_PROVIDER = 5;

    public const FAIL_CUSTOM = 6;

    public const FAIL_RFC5322 = 7;

    /**
     * @var BasicValidator
     */
    private BasicValidator $basicValidator;

    /**
     * @var Rfc5322Validator
     */
    private Rfc5322Validator $rfc5322Validator;

    /**
     * @var MxValidator
     */
    private MxValidator $mxValidator;

    /**
     * @var BannedListValidator
     */
    private BannedListValidator $bannedListValidator;

    /**
     * @var DisposableEmailValidator
     */
    private DisposableEmailValidator $disposableEmailValidator;

    /**
     * @var FreeEmailValidator
     */
    private FreeEmailValidator $freeEmailValidator;

    /**
     * @var GmailValidator
     */
    private GmailValidator $gmailValidator;

    /**
     * @var array<AValidator>
     * @since 2.0.0
     */
    private array $customValidators = [];

    /**
     * @var int
     */
    private int $reason;

    /**
     * @var EmailAddress|null
     * @since 1.1.0
     */
    private ?EmailAddress $emailAddress = null;

    public function __construct(array $config = [])
    {
        $this->reason = self::NO_ERROR;

        $policy = new Policy($config);

        $this->mxValidator = new MxValidator($policy);
        $this->basicValidator = new BasicValidator($policy);
        $this->rfc5322Validator = new Rfc5322Validator($policy);
        $this->bannedListValidator = new BannedListValidator($policy);
        $this->disposableEmailValidator = new DisposableEmailValidator($policy);
        $this->freeEmailValidator = new FreeEmailValidator($policy);
        $this->gmailValidator = new GmailValidator($policy);
    }

    /**
     * Register a custom validator
     *
     * @param AValidator $validator
     * @return void
     * @since 2.0.0
     */
    public function registerValidator(AValidator $validator): void
    {
        $this->customValidators[] = $validator;
    }

    /**
     * Validate an email address by the rules set forth in the Policy
     *
     * @param string $email
     * @return bool
     */
    public function validate(string $email): bool
    {
        $this->resetErrorCode();

        $this->emailAddress = new EmailAddress($email);

        if (!$this->basicValidator->validate($this->emailAddress)) {
            $this->reason = self::FAIL_BASIC;
        } elseif (!$this->rfc5322Validator->validate($this->emailAddress)) {
            $this->reason = self::FAIL_RFC5322;
        } elseif (!$this->mxValidator->validate($this->emailAddress)) {
            $this->reason = self::FAIL_MX_RECORD;
        } elseif (!$this->bannedListValidator->validate($this->emailAddress)) {
            $this->reason = self::FAIL_BANNED_DOMAIN;
        } elseif (!$this->disposableEmailValidator->validate($this->emailAddress)) {
            $this->reason = self::FAIL_DISPOSABLE_DOMAIN;
        } elseif (!$this->freeEmailValidator->validate($this->emailAddress)) {
            $this->reason = self::FAIL_FREE_PROVIDER;
        } else {
            foreach ($this->customValidators as $validator) {
                if (!$validator->validate($this->emailAddress)) {
                    $this->reason = self::FAIL_CUSTOM;
                    break;
                }
            }
        }

        return $this->reason === self::NO_ERROR;
    }

    /**
     * Returns the error code constant value for invalid email addresses.
     *
     * For use by integrating systems to create their own error messages.
     *
     * @since 1.0.1
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->reason;
    }

    /**
     * Returns an error message for invalid email addresses
     *
     * @return string
     */
    public function getErrorReason(): string
    {
        switch ($this->reason) {
            case self::FAIL_BASIC:
                $msg = 'Invalid format';
                break;
            case self::FAIL_RFC5322:
                $msg = 'Does not comply with RFC 5322';
                break;
            case self::FAIL_MX_RECORD:
                $msg = 'Domain does not accept email';
                break;
            case self::FAIL_BANNED_DOMAIN:
                $msg = 'Domain is banned';
                break;
            case self::FAIL_DISPOSABLE_DOMAIN:
                $msg = 'Domain is used by disposable email providers';
                break;
            case self::FAIL_FREE_PROVIDER:
                $msg = 'Domain is used by free email providers';
                break;
            case self::FAIL_CUSTOM:
                $msg = 'Failed custom validation';
                break;
            case self::NO_ERROR:
            default:
                $msg = '';
        }

        return $msg;
    }

    /**
     * Resets the error code so each validation starts off defaulting to "valid"
     *
     * @since 1.0.2
     * @return void
     */
    private function resetErrorCode(): void
    {
        $this->reason = self::NO_ERROR;
    }

    /**
     * Determines if a gmail account is using the "plus trick".
     *
     * @codeCoverageIgnore
     * @since 1.1.0
     * @return bool
     */
    public function isGmailWithPlusChar(): bool
    {
        return $this->emailAddress !== null && $this->gmailValidator->isGmailWithPlusChar($this->emailAddress);
    }

    /**
     * Returns a gmail address with the "plus trick" portion of the email address.
     *
     * @codeCoverageIgnore
     * @since 1.1.0
     * @return string
     */
    public function getGmailAddressWithoutPlus(): string
    {
        if ($this->emailAddress === null) {
            return '';
        }
        return $this->gmailValidator->getGmailAddressWithoutPlus($this->emailAddress);
    }

    /**
     * Returns a sanitized gmail address (plus trick removed and dots removed).
     *
     * @codeCoverageIgnore
     * @since 1.1.4
     * @return string
     */
    public function getSanitizedGmailAddress(): string
    {
        if ($this->emailAddress === null) {
            return '';
        }
        return $this->gmailValidator->getSanitizedGmailAddress($this->emailAddress);
    }
}
