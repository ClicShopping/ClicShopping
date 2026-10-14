<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;
use EmailValidator\Policy;

/**
 * Validates and transforms Gmail email addresses
 * 
 * This validator handles Gmail-specific functionality including:
 * - Detection of Gmail addresses using the "plus trick"
 * - Transformation of Gmail addresses to remove the plus trick
 * - Sanitization of Gmail addresses by removing dots
 * 
 * @since 2.0.0
 */
class GmailValidator extends AValidator
{
    /**
     * Validates if an email address is a Gmail address
     * 
     * @param EmailAddress $email The email address to validate
     * @return bool True if the email is a valid Gmail address, false otherwise
     * @since 2.0.0
     */
    public function validate(EmailAddress $email): bool
    {
        $domain = $email->getDomain();
        return $domain !== null && in_array($domain, ['gmail.com', 'googlemail.com'], true);
    }

    /**
     * Determines if a Gmail account is using the "plus trick"
     * 
     * @param EmailAddress $email The email address to check
     * @return bool True if the Gmail address uses the plus trick, false otherwise
     * @since 2.0.0
     */
    public function isGmailWithPlusChar(EmailAddress $email): bool
    {
        return $this->validate($email) && $email->isGmailWithPlusChar();
    }

    /**
     * Returns a Gmail address with the "plus trick" portion removed
     * 
     * @param EmailAddress $email The email address to transform
     * @return string The Gmail address without the plus trick portion
     * @since 2.0.0
     */
    public function getGmailAddressWithoutPlus(EmailAddress $email): string
    {
        return $this->validate($email) ? $email->getGmailAddressWithoutPlus() : $email->getEmailAddress();
    }

    /**
     * Returns a sanitized Gmail address (plus trick removed and dots removed)
     * 
     * @param EmailAddress $email The email address to sanitize
     * @return string The sanitized Gmail address
     * @since 2.0.0
     */
    public function getSanitizedGmailAddress(EmailAddress $email): string
    {
        return $this->validate($email) ? $email->getSanitizedGmailAddress() : $email->getEmailAddress();
    }
} 