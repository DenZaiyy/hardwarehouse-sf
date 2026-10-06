<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testFullNameSnapshotNeverExceedsItsColumn(): void
    {
        // Prénom et nom de 50 caractères chacun, plus l'espace : 101 caractères pour une colonne de 100
        $order = (new Order())->setUserFullNameSnapshot(str_repeat('é', 50).' '.str_repeat('é', 50));

        self::assertSame(100, mb_strlen((string) $order->getUserFullNameSnapshot()));
    }
}
