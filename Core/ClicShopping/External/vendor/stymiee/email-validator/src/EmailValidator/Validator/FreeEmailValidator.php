<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

use EmailValidator\EmailAddress;

/**
 * Validates email addresses against known free email providers
 *
 * This validator checks if an email address is from a known free email provider.
 * It maintains a list of free email domains from multiple sources and can be
 * configured to use only local lists or fetch from remote sources.
 */
class FreeEmailValidator extends AProviderValidator
{
    /**
     * Array of client-provided free email providers
     *
     * @var array<string> Array of client-provided free email providers
     */
    protected array $freeEmailListProviders = [];

    /**
     * Array of URLs containing lists of free email addresses and their formats
     *
     * @var array<array{format: string, url: string}> Array of URLs containing a list of free email addresses and the format of that list
     */
    protected static array $providers = [
        [
            'format' => 'txt',
            'url' => 'https://gist.githubusercontent.com/tbrianjones/5992856/raw/93213efb652749e226e69884d6c048e595c1280a/free_email_provider_domains.txt'
        ],
    ];

    /**
     * Validates an email address against known free email providers
     *
     * Checks if validating against free email domains is enabled. If so, gets the list of email domains
     * and checks if the domain is one of them.
     *
     * @param EmailAddress $email The email address to validate
     * @return bool True if the domain is not a free email provider or validation is disabled, false if it is a free provider
     */
    public function validate(EmailAddress $email): bool
    {
        if (!$this->policy->checkFreeEmail()) {
            return true;
        }

        if ($this->freeEmailListProviders === []) {
            $this->freeEmailListProviders = $this->getList(
                $this->policy->checkFreeLocalListOnly(),
                $this->policy->getFreeList()
            );
        }

        $domain = $email->getDomain();
        if ($domain === null) {
            return true;
        }

        return !in_array($domain, $this->freeEmailListProviders, true);
    }
}
