<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ComponentSlot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Composant d'une configuration sauvegardée, avec les instantanés qui permettent d'afficher Mes configurations sans
 * appeler l'API pour chaque composant. Les quantités permises dépendent de la carte mère choisie : elles sont
 * vérifiées par ConfiguratorCompletenessManager, pas par la base.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_configuration_item_configuration', columns: ['configuration_id'])]
class ConfigurationItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Configuration $configuration = null;

    #[ORM\Column(length: 20, enumType: ComponentSlot::class)]
    private ?ComponentSlot $slot = null;

    /** Identifiant MongoDB du produit dans l'API catalogue. */
    #[ORM\Column(length: 255)]
    private ?string $productId = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(length: 100)]
    private ?string $productNameSnapshot = null;

    #[ORM\Column(length: 255)]
    private ?string $productSlugSnapshot = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $productImageSnapshot = null;

    /** Prix lors de la sauvegarde, à titre de repère : l'ajout au panier relit toujours le prix dans l'API. */
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $unitPriceSnapshot = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfiguration(): ?Configuration
    {
        return $this->configuration;
    }

    public function setConfiguration(?Configuration $configuration): static
    {
        $this->configuration = $configuration;

        return $this;
    }

    public function getSlot(): ?ComponentSlot
    {
        return $this->slot;
    }

    public function setSlot(ComponentSlot $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getProductNameSnapshot(): ?string
    {
        return $this->productNameSnapshot;
    }

    public function setProductNameSnapshot(string $productNameSnapshot): static
    {
        $this->productNameSnapshot = $productNameSnapshot;

        return $this;
    }

    public function getProductSlugSnapshot(): ?string
    {
        return $this->productSlugSnapshot;
    }

    public function setProductSlugSnapshot(string $productSlugSnapshot): static
    {
        $this->productSlugSnapshot = $productSlugSnapshot;

        return $this;
    }

    public function getProductImageSnapshot(): ?string
    {
        return $this->productImageSnapshot;
    }

    public function setProductImageSnapshot(?string $productImageSnapshot): static
    {
        $this->productImageSnapshot = $productImageSnapshot;

        return $this;
    }

    public function getUnitPriceSnapshot(): ?string
    {
        return $this->unitPriceSnapshot;
    }

    public function setUnitPriceSnapshot(string $unitPriceSnapshot): static
    {
        $this->unitPriceSnapshot = $unitPriceSnapshot;

        return $this;
    }
}
