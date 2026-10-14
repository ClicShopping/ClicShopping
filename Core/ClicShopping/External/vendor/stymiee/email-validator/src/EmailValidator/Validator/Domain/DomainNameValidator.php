<?php

declare(strict_types=1);

namespace EmailValidator\Validator\Domain;

/**
 * Validates domain names according to RFC 5322 standards
 */
class DomainNameValidator
{
    private const MAX_DOMAIN_LABEL_LENGTH = 63;
    private const MAX_DOMAIN_LENGTH = 255;

    /**
     * Validates a domain name
     *
     * @param string $domain The domain name to validate
     * @return bool True if the domain name is valid
     */
    public function validate(string $domain): bool
    {
        // Check for empty domain
        if ($domain === '') {
            return false;
        }

        // Check total length
        if (!$this->validateLength($domain)) {
            return false;
        }

        // Split into labels
        $labels = explode('.', $domain);

        // Must have at least two labels
        if (count($labels) < 2) {
            return false;
        }

        // Validate each label
        foreach ($labels as $label) {
            if (!$this->validateLabel($label)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates the length of a domain
     *
     * @param string $domain The domain to validate
     * @return bool True if the length is valid
     */
    private function validateLength(string $domain): bool
    {
        return strlen($domain) <= self::MAX_DOMAIN_LENGTH;
    }

    /**
     * Validates a single domain label
     *
     * @param string $label The domain label to validate
     * @return bool True if the domain label is valid
     */
    private function validateLabel(string $label): bool
    {
        // Check length
        if (!$this->validateLabelLength($label)) {
            return false;
        }

        // Handle IDN labels (starting with 'xn--')
        if (substr($label, 0, 4) === 'xn--') {
            return $this->validateIDNLabel($label);
        }

        // Single character labels are allowed if they're alphanumeric
        if (strlen($label) === 1) {
            return ctype_alnum($label);
        }

        // Must start and end with alphanumeric
        if (!$this->hasValidLabelBoundaries($label)) {
            return false;
        }

        // Check for valid characters and format
        if (!$this->hasValidLabelFormat($label)) {
            return false;
        }

        // Check for consecutive hyphens
        return !$this->hasConsecutiveHyphens($label);
    }

    /**
     * Validates the length of a domain label
     *
     * @param string $label The domain label to validate
     * @return bool True if the length is valid
     */
    private function validateLabelLength(string $label): bool
    {
        return strlen($label) <= self::MAX_DOMAIN_LABEL_LENGTH && $label !== '';
    }

    /**
     * Validates an IDN (Internationalized Domain Name) label
     *
     * @param string $label The IDN label to validate
     * @return bool True if the IDN label is valid
     */
    private function validateIDNLabel(string $label): bool
    {
        // Must be at least 5 characters (xn-- plus at least one character)
        if (strlen($label) < 5) {
            return false;
        }

        // Rest of the label must be alphanumeric or hyphen
        $rest = substr($label, 4);
        return (bool)preg_match('/^[a-zA-Z0-9-]+$/', $rest);
    }

    /**
     * Checks if a domain label has valid start and end characters
     *
     * @param string $label The domain label to validate
     * @return bool True if the boundaries are valid
     */
    private function hasValidLabelBoundaries(string $label): bool
    {
        return ctype_alnum($label[0]) && ctype_alnum(substr($label, -1));
    }

    /**
     * Checks if a domain label has valid format
     *
     * @param string $label The domain label to validate
     * @return bool True if the format is valid
     */
    private function hasValidLabelFormat(string $label): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/', $label);
    }

    /**
     * Checks if a domain label has consecutive hyphens
     *
     * @param string $label The domain label to validate
     * @return bool True if the label has consecutive hyphens
     */
    private function hasConsecutiveHyphens(string $label): bool
    {
        return strpos($label, '--') !== false;
    }
} 