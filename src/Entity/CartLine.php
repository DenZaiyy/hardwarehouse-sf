<?php

namespace App\Entity;

use App\Repository\CartLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartLineRepository::class)]
class CartLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $productId = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private ?string $unitPriceSnapshot = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $discountPriceSnapshot = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $discountAmountSnapshot = null;

    #[ORM\Column(length: 100)]
    private ?string $productNameSnapshot = null;

    #[ORM\Column(length: 255)]
    private ?string $productSlugSnapshot = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $productImageSnapshot = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $productCategorySnapshot = null;

    #[ORM\Column]
    private ?int $stockSnapshot = null;

    #[ORM\ManyToOne(inversedBy: 'cartLines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cart $cart = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUnitPriceSnapshot(): ?string
    {
        return $this->unitPriceSnapshot;
    }

    public function setUnitPriceSnapshot(string $unitPriceSnapshot): static
    {
        $this->unitPriceSnapshot = $unitPriceSnapshot;

        return $this;
    }

    public function getDiscountPriceSnapshot(): ?string
    {
        return $this->discountPriceSnapshot;
    }

    public function setDiscountPriceSnapshot(?string $discountPriceSnapshot): static
    {
        $this->discountPriceSnapshot = $discountPriceSnapshot;

        return $this;
    }

    public function getDiscountAmountSnapshot(): ?string
    {
        return $this->discountAmountSnapshot;
    }

    public function setDiscountAmountSnapshot(?string $discountAmountSnapshot): static
    {
        $this->discountAmountSnapshot = $discountAmountSnapshot;

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

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

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

    public function getProductCategorySnapshot(): ?string
    {
        return $this->productCategorySnapshot;
    }

    public function setProductCategorySnapshot(?string $productCategorySnapshot): static
    {
        $this->productCategorySnapshot = $productCategorySnapshot;

        return $this;
    }

    public function getStockSnapshot(): ?int
    {
        return $this->stockSnapshot;
    }

    public function setStockSnapshot(int $stockSnapshot): static
    {
        $this->stockSnapshot = $stockSnapshot;

        return $this;
    }
}
