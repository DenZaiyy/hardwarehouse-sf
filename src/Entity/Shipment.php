<?php

namespace App\Entity;

use App\Enum\ShipmentStatus;
use App\Repository\ShipmentRepository;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
class Shipment
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expedition_date = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $delivery_date = null;

    #[ORM\Column(length: 100)]
    private ?string $tracking_number = null;

    #[ORM\ManyToOne(inversedBy: 'shipments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Carrier $carrier = null;

    #[ORM\ManyToOne(inversedBy: 'shipment')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\Column(enumType: ShipmentStatus::class)]
    private ?ShipmentStatus $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpeditionDate(): ?\DateTimeImmutable
    {
        return $this->expedition_date;
    }

    public function setExpeditionDate(?\DateTimeImmutable $expedition_date): static
    {
        $this->expedition_date = $expedition_date;

        return $this;
    }

    public function getDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->delivery_date;
    }

    public function setDeliveryDate(?\DateTimeImmutable $delivery_date): static
    {
        $this->delivery_date = $delivery_date;

        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->tracking_number;
    }

    public function setTrackingNumber(string $tracking_number): static
    {
        $this->tracking_number = $tracking_number;

        return $this;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): static
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getStatus(): ?ShipmentStatus
    {
        return $this->status;
    }

    public function setStatus(ShipmentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
