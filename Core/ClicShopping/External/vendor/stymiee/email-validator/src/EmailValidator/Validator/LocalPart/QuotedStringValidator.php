<?php

declare(strict_types=1);

namespace EmailValidator\Validator\LocalPart;

/**
 * Validates quoted string format of local parts in email addresses
 */
class QuotedStringValidator
{
    /**
     * Characters that must be escaped in quoted strings
     */
    private const MUST_ESCAPE = ['"', '\\'];

    /**
     * Validates a quoted string local part
     *
     * @param string $localPart The quoted string to validate
     * @return bool True if the quoted string is valid
     */
    public function validate(string $localPart): bool
    {
        // Must start and end with quotes
        if (!$this->hasValidQuotes($localPart)) {
            return false;
        }

        // Remove outer quotes for content validation
        $content = substr($localPart, 1, -1);

        // Empty quoted strings are valid
        if ($content === '') {
            return true;
        }

        return $this->validateContent($content);
    }

    /**
     * Checks if a quoted string has valid opening and closing quotes
     *
     * @param string $localPart The quoted string to validate
     * @return bool True if the quotes are valid
     */
    private function hasValidQuotes(string $localPart): bool
    {
        return substr($localPart, 0, 1) === '"' && substr($localPart, -1) === '"';
    }

    /**
     * Validates the content of a quoted string
     *
     * @param string $content The content to validate (without outer quotes)
     * @return bool True if the content is valid
     */
    private function validateContent(string $content): bool
    {
        $length = strlen($content);
        $i = 0;

        while ($i < $length) {
            $char = $content[$i];
            $charCode = ord($char);

            // Handle backslash escapes
            if ($char === '\\') {
                // Can't end with a lone backslash
                if ($i === $length - 1) {
                    return false;
                }
                // Next character must be either a quote or backslash
                $nextChar = $content[$i + 1];
                if (!in_array($nextChar, self::MUST_ESCAPE, true)) {
                    return false;
                }
                // Skip the escaped character
                $i += 2;
                continue;
            }

            // Non-printable characters are never allowed (except tab)
            if (($charCode < 32 || $charCode > 126) && $charCode !== 9) {
                return false;
            }

            // Unescaped quotes are not allowed
            if ($char === '"') {
                return false;
            }

            $i++;
        }

        return true;
    }
} 