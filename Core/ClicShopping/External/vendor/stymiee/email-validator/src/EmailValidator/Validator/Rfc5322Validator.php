<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;
use EmailValidator\Validator\LocalPart\AtomValidator;
use EmailValidator\Validator\LocalPart\QuotedStringValidator;
use EmailValidator\Validator\Domain\DomainNameValidator;
use EmailValidator\Validator\Domain\DomainLiteralValidator;

/**
 * Main validator class that implements RFC 5322 email validation
 */
class Rfc5322Validator
{
    private const MAX_LOCAL_PART_LENGTH = 64;

    private AtomValidator $atomValidator;
    private QuotedStringValidator $quotedStringValidator;
    private DomainNameValidator $domainNameValidator;
    private DomainLiteralValidator $domainLiteralValidator;

    /**
     * Constructor initializes all specialized validators
     */
    public function __construct()
    {
        $this->atomValidator = new AtomValidator();
        $this->quotedStringValidator = new QuotedStringValidator();
        $this->domainNameValidator = new DomainNameValidator();
        $this->domainLiteralValidator = new DomainLiteralValidator();
    }

    /**
     * Validates an email address according to RFC 5322
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the email address is valid
     */
    public function validate(EmailAddress $email): bool
    {
        $localPart = $email->getLocalPart();
        $domain = $email->getDomain();

        if ($localPart === null || $domain === null) {
            return false;
        }

        return $this->validateLocalPart($localPart) && $this->validateDomain($domain);
    }

    /**
     * Validates the local part of an email address
     *
     * @param string $localPart The local part to validate
     * @return bool True if the local part is valid
     */
    private function validateLocalPart(string $localPart): bool
    {
        // Empty local part is invalid
        if ($localPart === '') {
            return false;
        }

        // Check length
        if (strlen($localPart) > self::MAX_LOCAL_PART_LENGTH) {
            return false;
        }

        // Check if it's a quoted string
        if (substr($localPart, 0, 1) === '"') {
            return $this->quotedStringValidator->validate($localPart);
        }

        // Otherwise, treat as dot-atom
        return $this->atomValidator->validate($localPart);
    }

    /**
     * Validates the domain part of an email address
     *
     * @param string $domain The domain to validate
     * @return bool True if the domain is valid
     */
    private function validateDomain(string $domain): bool
    {
        // Empty domain is invalid
        if ($domain === '') {
            return false;
        }

        // Check if it's a domain literal
        if (substr($domain, 0, 1) === '[') {
            return $this->domainLiteralValidator->validate($domain);
        }

        // Otherwise, treat as domain name
        return $this->domainNameValidator->validate($domain);
    }
}
