<?php

declare(strict_types=1);

namespace EmailValidator;

class EmailAddress
{
    /**
     * @var string
     */
    private string $email;

    /**
     * @var string|null
     */
    private ?string $localPart = null;

    /**
     * @var string|null
     */
    private ?string $domain = null;

    /**
     * @var array<string>
     */
    private array $comments = [];

    public function __construct(string $email)
    {
        $this->email = $email;
        $this->parseEmail();
    }

    /**
     * Parses the email address into local part and domain
     *
     * This method handles:
     * - Multiple @ symbols in quoted strings
     * - Domain literals (IP addresses in square brackets)
     * - Comments in email addresses
     *
     * @return void
     */
    private function parseEmail(): void
    {
        $email = $this->email;

        // Extract comments while preserving their positions
        $email = $this->extractComments($email);

        // Split on the last @ symbol
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return;
        }

        $this->localPart = substr($email, 0, $atPos);
        $this->domain = substr($email, $atPos + 1);
    }

    /**
     * Extracts comments from an email address while preserving their positions
     *
     * @param string $email The email address to process
     * @return string The email address with comments removed
     */
    private function extractComments(string $email): string
    {
        $result = '';
        $inComment = false;
        $commentDepth = 0;
        $currentComment = '';
        $escaped = false;

        for ($i = 0, $iMax = strlen($email); $i < $iMax; $i++) {
            $char = $email[$i];

            if ($escaped) {
                if ($inComment) {
                    $currentComment .= $char;
                } else {
                    $result .= $char;
                }
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                if ($inComment) {
                    $currentComment .= $char;
                } else {
                    $result .= $char;
                }
                continue;
            }

            if ($char === '(') {
                if ($inComment) {
                    $commentDepth++;
                    $currentComment .= $char;
                } else {
                    $inComment = true;
                    $commentDepth = 1;
                }
                continue;
            }

            if ($char === ')') {
                if ($inComment) {
                    $commentDepth--;
                    if ($commentDepth === 0) {
                        $this->comments[] = $currentComment;
                        $currentComment = '';
                        $inComment = false;
                    } else {
                        $currentComment .= $char;
                    }
                } else {
                    $result .= $char;
                }
                continue;
            }

            if ($inComment) {
                $currentComment .= $char;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Returns the domain name portion of the email address.
     *
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Returns the local part of the email address.
     *
     * @return string|null
     */
    public function getLocalPart(): ?string
    {
        return $this->localPart;
    }

    /**
     * Returns the complete email address.
     *
     * @return string
     */
    public function getEmailAddress(): string
    {
        return $this->email;
    }

    /**
     * Returns any comments found in the email address.
     *
     * @return array<string>
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * Returns the username portion of the email address.
     *
     * @since 1.1.0
     * @return string
     */
    private function getUsername(): string
    {
        return $this->localPart ?? '';
    }

    /**
     * Determines if a gmail account is using the "plus trick".
     *
     * @since 1.1.0
     * @return bool
     */
    public function isGmailWithPlusChar(): bool
    {
        $result = false;
        if (in_array($this->getDomain(), ['gmail.com', 'googlemail.com'])) {
            $result = strpos($this->getUsername(), '+') !== false;
        }

        return $result;
    }

    /**
     * Returns a gmail address without the "plus trick" portion of the email address.
     *
     * @since 1.1.0
     * @return string
     */
    public function getGmailAddressWithoutPlus(): string
    {
        return preg_replace('/^(.+?)(\+.+?)(@.+)/', '$1$3', $this->getEmailAddress());
    }

    /**
     * Returns a gmail address without the "plus trick" portion of the email address and all dots removed.
     *
     * @since 1.1.4
     * @return string
     */
    public function getSanitizedGmailAddress(): string
    {
        $email = new EmailAddress($this->getGmailAddressWithoutPlus());
        return sprintf(
            '%s@%s',
            str_replace('.', '', $email->getUsername()),
            $email->getDomain()
        );
    }
}
