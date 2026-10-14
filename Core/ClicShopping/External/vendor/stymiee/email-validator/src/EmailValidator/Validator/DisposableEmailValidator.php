<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;

/**
 * Validates email addresses against known disposable email providers
 *
 * This validator checks if an email address is from a known disposable email provider.
 * It maintains a list of disposable email domains from multiple sources and can be
 * configured to use only local lists or fetch from remote sources.
 */
class DisposableEmailValidator extends AProviderValidator
{
    /**
     * Array of client-provided disposable email providers
     *
     * @var array<string> Array of client-provided disposable email providers
     */
    protected array $disposableEmailListProviders = [];

    /**
     * Array of URLs containing lists of disposable email addresses and their formats
     *
     * @var array<array{format: string, url: string}> Array of URLs containing a list of disposable email addresses and the format of that list
     */
    protected static array $providers = [
        [
            'format' => 'txt',
            'url' => 'https://raw.githubusercontent.com/martenson/disposable-email-domains/master/disposable_email_blocklist.conf'
        ],
        [
            'format' => 'json',
            'url' => 'https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/wildcard.json'
        ],
    ];

    /**
     * Validates an email address against known disposable email providers
     *
     * Checks if validating against disposable domains is enabled. If so, gets the list of disposable domains
     * and checks if the domain is one of them.
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the domain is not a disposable email provider or validation is disabled, false if it is a disposable provider
     */
    public function validate(EmailAddress $email): bool
    {
        if (!$this->policy->checkDisposableEmail()) {
            return true;
        }

        if ($this->disposableEmailListProviders === []) {
            $this->disposableEmailListProviders = $this->getList(
                $this->policy->checkDisposableLocalListOnly(),
                $this->policy->getDisposableList()
            );
        }

        $domain = $email->getDomain();
        if ($domain === null) {
            return true;
        }

        return !in_array($domain, $this->disposableEmailListProviders, true);
    }
}
