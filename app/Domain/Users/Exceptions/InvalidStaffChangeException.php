<?php

namespace App\Domain\Users\Exceptions;

use DomainException;

/**
 * Modificare de personal respinsă de o regulă de business. `$field` este
 * câmpul de formular la care se leagă mesajul.
 */
final class InvalidStaffChangeException extends DomainException
{
    public function __construct(string $message, public readonly string $field = 'role_names')
    {
        parent::__construct($message);
    }
}
