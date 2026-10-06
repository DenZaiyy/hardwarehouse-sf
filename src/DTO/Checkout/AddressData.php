<?php

namespace App\DTO\Checkout;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Longueurs maximales alignées sur OrderAddress, où l'adresse est recopiée à la commande.
 */
final class AddressData
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    public ?string $label = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $firstName = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $lastName = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $address1 = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    public ?string $postcode = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $city = null;

    #[Assert\NotBlank]
    public ?string $country = null;
}
