<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;

/**
 * Validates email addresses against a list of banned domains
 *
 * This validator checks if the domain of an email address is in a list of banned domains.
 * The validation is only performed if enabled in the policy. Domain matching supports
 * wildcard patterns for flexible banning rules.
 */
class BannedListValidator extends AValidator
{
    /**
     * Validates an email address against the banned domains list
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the domain is not banned or validation is disabled, false if the domain is banned
     */
    public function validate(EmailAddress $email): bool
    {
        if ($this->policy->checkBannedListedEmail()) {
            $domain = $email->getDomain();
            foreach ($this->policy->getBannedList() as $bannedDomain) {
                if (fnmatch($bannedDomain, $domain ?? '')) {
                    return false;
                }
            }
        }
        return true;
    }
}
