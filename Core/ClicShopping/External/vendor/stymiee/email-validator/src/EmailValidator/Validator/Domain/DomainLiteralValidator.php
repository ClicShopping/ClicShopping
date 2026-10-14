<?php

declare(strict_types=1);

namespace EmailValidator\Validator\Domain;

/**
 * Validates domain literals (IPv4 and IPv6 addresses) according to RFC 5322
 */
class DomainLiteralValidator
{
    /**
     * Validates a domain literal
     *
     * @param string $domain The domain literal to validate
     * @return bool True if the domain literal is valid
     */
    public function validate(string $domain): bool
    {
        // Must be enclosed in square brackets
        if (!$this->hasValidBrackets($domain)) {
            return false;
        }

        // Remove brackets for content validation
        $content = substr($domain, 1, -1);

        // Empty domain literals are invalid
        if ($content === '') {
            return false;
        }

        // Check for whitespace or control characters
        if ($this->hasInvalidCharacters($content)) {
            return false;
        }

        // Try IPv4 first, then IPv6
        return $this->validateIPv4($content) || $this->validateIPv6($content);
    }

    /**
     * Checks if a domain literal has valid opening and closing brackets
     *
     * @param string $domain The domain literal to validate
     * @return bool True if the brackets are valid
     */
    private function hasValidBrackets(string $domain): bool
    {
        return substr($domain, 0, 1) === '[' && substr($domain, -1) === ']';
    }

    /**
     * Checks for whitespace or control characters
     *
     * @param string $content The content to check
     * @return bool True if invalid characters are found
     */
    private function hasInvalidCharacters(string $content): bool
    {
        return (bool)preg_match('/[\s\x00-\x1F\x7F]/', $content);
    }

    /**
     * Validates an IPv4 address
     *
     * @param string $address The IPv4 address to validate
     * @return bool True if the IPv4 address is valid
     */
    private function validateIPv4(string $address): bool
    {
        // Split into octets
        $octets = explode('.', $address);

        // Must have exactly 4 octets
        if (count($octets) !== 4) {
            return false;
        }

        // Validate each octet
        foreach ($octets as $octet) {
            if (!$this->validateIPv4Octet($octet)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a single IPv4 octet
     *
     * @param string $octet The octet to validate
     * @return bool True if the octet is valid
     */
    private function validateIPv4Octet(string $octet): bool
    {
        // Empty octets are invalid
        if ($octet === '') {
            return false;
        }

        // Must be numeric and in valid range
        if (!is_numeric($octet)) {
            return false;
        }

        $value = (int)$octet;
        return $value >= 0 && $value <= 255;
    }

    /**
     * Validates an IPv6 address
     *
     * @param string $address The IPv6 address to validate
     * @return bool True if the IPv6 address is valid
     */
    private function validateIPv6(string $address): bool
    {
        // Must start with 'IPv6:' (case-sensitive)
        if (substr($address, 0, 5) !== 'IPv6:') {
            return false;
        }

        // Remove prefix
        $address = substr($address, 5);

        // Handle compressed notation
        if (strpos($address, '::') !== false) {
            return $this->validateCompressedIPv6($address);
        }

        // Split into groups
        $groups = explode(':', $address);

        // Must have exactly 8 groups for uncompressed notation
        if (count($groups) !== 8) {
            return false;
        }

        // Validate each group
        foreach ($groups as $group) {
            if (!$this->validateIPv6Group($group)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a compressed IPv6 address
     *
     * @param string $address The IPv6 address to validate (without prefix)
     * @return bool True if the IPv6 address is valid
     */
    private function validateCompressedIPv6(string $address): bool
    {
        // Only one :: allowed
        if (substr_count($address, '::') > 1) {
            return false;
        }

        // Split on ::
        $parts = explode('::', $address);
        if (count($parts) !== 2) {
            return false;
        }

        // Split each part into groups
        $leftGroups = $parts[0] ? explode(':', $parts[0]) : [];
        $rightGroups = $parts[1] ? explode(':', $parts[1]) : [];

        // Calculate total groups
        $totalGroups = count($leftGroups) + count($rightGroups);
        if ($totalGroups >= 8) {
            return false;
        }

        // Validate left groups
        foreach ($leftGroups as $group) {
            if (!$this->validateIPv6Group($group)) {
                return false;
            }
        }

        // Validate right groups
        foreach ($rightGroups as $group) {
            if (!$this->validateIPv6Group($group)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a single IPv6 group
     *
     * @param string $group The group to validate
     * @return bool True if the group is valid
     */
    private function validateIPv6Group(string $group): bool
    {
        // Empty groups are invalid
        if ($group === '') {
            return false;
        }

        // Must be 1-4 hexadecimal digits (case-insensitive)
        return (bool)preg_match('/^[0-9A-Fa-f]{1,4}$/', $group);
    }
} 