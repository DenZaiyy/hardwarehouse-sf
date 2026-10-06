<?php

namespace App\DTO\Checkout;

use App\Validator\AvailableAccountEmail;
use App\Validator\PasswordRequirements;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Le prénom et le nom servent aussi d'adresse par défaut (OrderAddress, 50 caractères) ;
 * l'e-mail reprend la longueur de User::$email.
 */
final class GuestIdentityData
{
    public ?string $title = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $firstName = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $lastName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Assert\When(expression: 'this.wantsAnAccount()', constraints: [new AvailableAccountEmail()])]
    public ?string $email = null;

    /** Facultatif : saisi, il crée un compte, avec la même politique que l'inscription. */
    #[Assert\When(expression: 'this.wantsAnAccount()', constraints: [new PasswordRequirements()])]
    public ?string $password = null;

    public function wantsAnAccount(): bool
    {
        return null !== $this->password && '' !== $this->password;
    }
}
