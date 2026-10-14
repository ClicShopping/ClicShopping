<?php

declare(strict_types=1);

namespace EmailValidator\Validator\LocalPart;

/**
 * Validates the atom format of local parts in email addresses
 */
class AtomValidator
{
    // Character sets for unquoted local part
    private const ALLOWED_CHARS = '!#$%&\'*+-/=?^_`{|}~.';

    /**
     * Validates a dot-atom format local part
     *
     * @param string $localPart The unquoted local part to validate
     * @return bool True if the unquoted local part is valid
     */
    public function validate(string $localPart): bool
    {
        // Empty local part is invalid
        if ($localPart === '') {
            return false;
        }

        // Split into atoms
        $atoms = explode('.', $localPart);

        // Check each atom
        foreach ($atoms as $atom) {
            if (!$this->validateAtom($atom)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a single atom
     *
     * @param string $atom The atom to validate
     * @return bool True if the atom is valid
     */
    private function validateAtom(string $atom): bool
    {
        if ($atom === '') {
            return false;
        }

        // Check for valid characters
        return (bool)preg_match('/^[a-zA-Z0-9!#$%&\'*+\-\/=?^_`{|}~]+$/', $atom);
    }
} 