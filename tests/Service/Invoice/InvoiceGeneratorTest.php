<?php

declare(strict_types=1);

namespace App\Tests\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderStatus;
use App\Service\Invoice\InvoiceGenerator;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Une facture française porte un numéro unique, chronologique et sans rupture : la numérotation
 * repart chaque année (FAC-2026-000001) et un échec ne consomme pas de numéro.
 */
final class InvoiceGeneratorTest extends KernelTestCase
{
    use CreatesShopEntities;

    public function testInvoicesOfTheYearAreNumberedWithoutGaps(): void
    {
        $first = $this->generator()->forOrder($this->confirmedOrder());
        $second = $this->generator()->forOrder($this->confirmedOrder());

        self::assertMatchesRegularExpression(\sprintf('/^FAC-%s-\d{6}$/', date('Y')), (string) $first->getReference());
        self::assertSame($this->number($first) + 1, $this->number($second));
    }

    public function testAnOrderGetsASingleInvoice(): void
    {
        $order = $this->confirmedOrder();

        self::assertSame($this->generator()->forOrder($order), $this->generator()->forOrder($order));
    }

    public function testInvoiceIsAPdfKeptOutOfThePublicDirectory(): void
    {
        $path = $this->generator()->absolutePath($this->generator()->forOrder($this->confirmedOrder()));

        self::assertFileExists($path);
        self::assertSame('%PDF', file_get_contents($path, length: 4));
        self::assertStringNotContainsString('/public/', $path);
    }

    private function confirmedOrder(): Order
    {
        $order = $this->createOrder($this->createUser())->setStatus(OrderStatus::CONFIRMED);
        $order->addOrderLine((new OrderLine())
            ->setProductId('produit-test')
            ->setProductName('AMD Ryzen 7 7800X3D')
            ->setProductSlug('amd-ryzen-7-7800x3d')
            ->setQuantity(1)
            ->setUnitPrice('100.00')
            ->setTaxRate('0.2')
            ->setLineTotal('120.00'));
        $this->entityManager()->flush();

        return $order;
    }

    private function number(Invoice $invoice): int
    {
        return (int) substr((string) $invoice->getReference(), -6);
    }

    private function generator(): InvoiceGenerator
    {
        return static::getContainer()->get(InvoiceGenerator::class);
    }
}
