<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Règle unique des mots de passe, la même à l'inscription, à la réinitialisation et au changement :
 * celle qu'annonce le formulaire, plus le refus d'un mot de passe déjà divulgué.
 */
#[\Attribute]
final class PasswordRequirements extends Compound
{
    /**
     * @param array<string, mixed> $options
     *
     * @return list<Assert\NotBlank|Assert\Regex|Assert\NotCompromisedPassword>
     */
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(message: 'user.password.not_blank'),
            // 12 à 32 caractères, dont au moins une majuscule, une minuscule, un chiffre et un caractère spécial
            new Assert\Regex(
                pattern: '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z\d]).{12,32}$/u',
                message: 'user.password.requirements',
            ),
            // Service Have I Been Pwned : une panne du service ne doit pas bloquer l'inscription
            new Assert\NotCompromisedPassword(skipOnError: true),
        ];
    }
}
