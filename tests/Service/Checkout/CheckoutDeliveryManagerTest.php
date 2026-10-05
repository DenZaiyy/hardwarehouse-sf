<?php

declare(strict_types=1);

namespace App\Tests\Service\Checkout;

use App\DTO\Checkout\CheckoutState;
use App\Entity\Carrier;
use App\Service\Checkout\CheckoutDeliveryManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CheckoutDeliveryManagerTest extends KernelTestCase
{
    public function testKnownCarrierCompletesTheDeliveryStep(): void
    {
        $carrier = (new Carrier())->setName('Colissimo')->setPrice('4.90');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($carrier);
        $entityManager->flush();

        $state = $this->deliveryManager()->saveCarrier(new CheckoutState(currentStep: 3), (int) $carrier->getId());

        self::assertTrue($state->deliveryCompleted);
        self::assertSame($carrier->getId(), $state->carrierId);
    }

    public function testUnknownCarrierLeavesTheDeliveryStepOpen(): void
    {
        // L'identifiant vient du navigateur : sans transporteur réel, OrderService compterait 0 € de port
        $state = $this->deliveryManager()->saveCarrier(new CheckoutState(currentStep: 3), 0);

        self::assertFalse($state->deliveryCompleted);
        self::assertNull($state->carrierId);
        self::assertSame(3, $state->currentStep);
    }

    private function deliveryManager(): CheckoutDeliveryManager
    {
        return static::getContainer()->get(CheckoutDeliveryManager::class);
    }
}
