<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConfigurationRepository;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configuration de PC sauvegardée par un client connecté (Mes configurations). Aucun statut de validité n'est
 * stocké : la compatibilité dépend d'un catalogue qui évolue, elle est recalculée à l'ouverture et avant l'ajout
 * au panier. Supprimée avec le compte de son propriétaire (ON DELETE CASCADE).
 */
#[ORM\Entity(repositoryClass: ConfigurationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_configuration_user', columns: ['user_id'])]
class Configuration
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, ConfigurationItem>
     */
    #[ORM\OneToMany(targetEntity: ConfigurationItem::class, mappedBy: 'configuration', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, ConfigurationItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ConfigurationItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setConfiguration($this);
        }

        return $this;
    }

    public function removeItem(ConfigurationItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
