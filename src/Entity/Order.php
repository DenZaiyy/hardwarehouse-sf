<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_REFERENCE', fields: ['reference'])]
#[UniqueEntity(fields: ['reference'], message: 'There is already an order with this reference')]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $userFullNameSnapshot = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    private ?User $user = null;

    /**
     * @var Collection<int, OrderLine>
     */
    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $orderLines;

    #[ORM\Column(length: 50, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\OneToOne(mappedBy: 'order', cascade: ['persist', 'remove'])]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $subtotal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $shippingAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $discountAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $taxAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalAmount = null;

    #[ORM\Column(length: 10)]
    private ?string $currency = null;

    /**
     * @var Collection<int, Shipment>
     */
    #[ORM\OneToMany(targetEntity: Shipment::class, mappedBy: 'order', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $shipment;

    /**
     * @var Collection<int, OrderAddress>
     */
    #[ORM\OneToMany(targetEntity: OrderAddress::class, mappedBy: 'order', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $orderAddresses;

    public function __construct()
    {
        $this->orderLines = new ArrayCollection();
        $this->shipment = new ArrayCollection();
        $this->orderAddresses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserFullNameSnapshot(): ?string
    {
        return $this->userFullNameSnapshot;
    }

    public function setUserFullNameSnapshot(string $userFullNameSnapshot): static
    {
        $this->userFullNameSnapshot = $userFullNameSnapshot;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, OrderLine>
     */
    public function getOrderLines(): Collection
    {
        return $this->orderLines;
    }

    public function addOrderLine(OrderLine $orderLine): static
    {
        if (!$this->orderLines->contains($orderLine)) {
            $this->orderLines->add($orderLine);
            $orderLine->setOrder($this);
        }

        return $this;
    }

    public function removeOrderLine(OrderLine $orderLine): static
    {
        if ($this->orderLines->removeElement($orderLine)) {
            // set the owning side to null (unless already changed)
            if ($orderLine->getOrder() === $this) {
                $orderLine->setOrder(null);
            }
        }

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): static
    {
        // set the owning side of the relation if necessary
        if ($invoice->getOrder() !== $this) {
            $invoice->setOrder($this);
        }

        $this->invoice = $invoice;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getShippingAmount(): ?string
    {
        return $this->shippingAmount;
    }

    public function setShippingAmount(string $shippingAmount): static
    {
        $this->shippingAmount = $shippingAmount;

        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Collection<int, Shipment>
     */
    public function getShipment(): Collection
    {
        return $this->shipment;
    }

    public function addShipment(Shipment $shipment): static
    {
        if (!$this->shipment->contains($shipment)) {
            $this->shipment->add($shipment);
            $shipment->setOrder($this);
        }

        return $this;
    }

    public function removeShipment(Shipment $shipment): static
    {
        if ($this->shipment->removeElement($shipment)) {
            // set the owning side to null (unless already changed)
            if ($shipment->getOrder() === $this) {
                $shipment->setOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderAddress>
     */
    public function getOrderAddresses(): Collection
    {
        return $this->orderAddresses;
    }

    public function addOrderAddress(OrderAddress $orderAddress): static
    {
        if (!$this->orderAddresses->contains($orderAddress)) {
            $this->orderAddresses->add($orderAddress);
            $orderAddress->setOrder($this);
        }

        return $this;
    }

    public function removeOrderAddress(OrderAddress $orderAddress): static
    {
        if ($this->orderAddresses->removeElement($orderAddress)) {
            // set the owning side to null (unless already changed)
            if ($orderAddress->getOrder() === $this) {
                $orderAddress->setOrder(null);
            }
        }

        return $this;
    }
}
