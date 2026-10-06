<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/** Adresse libre pour créer un compte : un compte existant se retrouve en se connectant. */
#[\Attribute]
final class AvailableAccountEmail extends Constraint
{
    public string $message = 'There is already an account with this email: log in to use it.';
}
