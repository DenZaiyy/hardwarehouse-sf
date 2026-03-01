<?php

namespace App\Entity;

use App\Enum\AddressType;
use App\Enum\CountryList;
use App\Repository\AddressRepository;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\GreaterThanOrEqual(value: 3)]
    private ?string $label = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstname = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^\d{1,4}(?:\s?(?:bis|ter|quater))?\s+(?:rue|avenue|boulevard|place|impasse|allée|chemin|route|passage|square|cours|quai|voie|résidence|lotissement|hameau)\s+.{2,}$/iu',
        message: 'Format d\'adresse invalide. Ex: 12 rue de la Paix'
    )]
    private ?string $address = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^\d{5}$/',
        message: 'Le code postal doit contenir 5 chiffres.'
    )]
    private ?string $cp = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $city = null;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user_info = null;

    #[ORM\Column(type: 'string', enumType: CountryList::class)]
    private ?CountryList $country = null;

    #[ORM\Column]
    private ?bool $is_default = null;

    #[ORM\Column(length: 20, enumType: AddressType::class)]
    private AddressType $type = AddressType::DELIVERY;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCp(): ?string
    {
        return $this->cp;
    }

    public function setCp(string $cp): static
    {
        $this->cp = $cp;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getUserInfo(): ?User
    {
        return $this->user_info;
    }

    public function setUserInfo(?User $user_info): static
    {
        $this->user_info = $user_info;

        return $this;
    }

    public function getCountry(): ?CountryList
    {
        return $this->country;
    }

    public function setCountry(CountryList $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->is_default;
    }

    public function setIsDefault(bool $is_default): static
    {
        $this->is_default = $is_default;

        return $this;
    }

    public function getType(): AddressType
    {
        return $this->type;
    }

    public function setType(AddressType $type): static
    {
        $this->type = $type;

        return $this;
    }
}
