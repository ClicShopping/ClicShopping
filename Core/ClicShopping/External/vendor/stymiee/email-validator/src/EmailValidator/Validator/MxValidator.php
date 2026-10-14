<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;

/**
 * Validates the MX records of an email domain
 *
 * This validator checks if the domain of an email address has valid MX records
 * configured, which is necessary for the domain to receive email. The validation
 * is only performed if enabled in the policy.
 */
class MxValidator extends AValidator
{
    /**
     * Validates the MX records of an email domain
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if MX records are valid or validation is disabled, false otherwise
     */
    public function validate(EmailAddress $email): bool
    {
        $valid = true;
        if ($this->policy->validateMxRecord()) {
            $domain = sprintf('%s.', $email->getDomain());
            $valid = checkdnsrr(idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46), 'MX');
        }
        return $valid;
    }
}
