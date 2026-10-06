<?php

namespace App\DTO\Checkout;

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
    public ?string $email = null;

    public ?string $password = null;
}
