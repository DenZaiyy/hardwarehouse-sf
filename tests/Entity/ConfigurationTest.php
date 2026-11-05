<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Configuration;
use App\Entity\ConfigurationItem;
use App\Enum\ComponentSlot;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Une configuration sauvegardée garde ses composants et leurs instantanés, et disparaît avec le compte de son
 * propriétaire : la base supprime en cascade, comme le prévoit le script de création du dossier.
 */
final class ConfigurationTest extends KernelTestCase
{
    use CreatesShopEntities;

    public function testSavedConfigurationKeepsItsComponents(): void
    {
        $owner = $this->createUser();
        $configuration = (new Configuration())->setName('Poste de jeu')->setUser($owner);
        $configuration->addItem($this->item(ComponentSlot::CPU, '665f1c2a9b1e4a0012345678', 1, '449.90'));
        $configuration->addItem($this->item(ComponentSlot::RAM, '665f1c2a9b1e4a0087654321', 2, '89.90'));
        $this->entityManager()->persist($configuration);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $saved = $this->entityManager()->find(Configuration::class, $configuration->getId());

        self::assertNotNull($saved);
        self::assertSame('Poste de jeu', $saved->getName());
        self::assertSame($owner->getId(), $saved->getUser()?->getId());
        self::assertNotNull($saved->getCreatedAt());
        self::assertCount(2, $saved->getItems());
        $ram = $saved->getItems()->findFirst(static fn (int $key, ConfigurationItem $item): bool => ComponentSlot::RAM === $item->getSlot());
        self::assertNotNull($ram);
        self::assertSame('665f1c2a9b1e4a0087654321', $ram->getProductId());
        self::assertSame(2, $ram->getQuantity());
        self::assertSame('89.90', $ram->getUnitPriceSnapshot());
        self::assertNull($ram->getProductImageSnapshot());
    }

    public function testDeletingTheOwnerDeletesTheirConfigurations(): void
    {
        $owner = $this->createUser();
        $configuration = (new Configuration())->setName('Bureautique')->setUser($owner);
        $configuration->addItem($this->item(ComponentSlot::CPU, '665f1c2a9b1e4a0012345678', 1, '199.90'));
        $this->entityManager()->persist($configuration);
        $this->entityManager()->flush();
        $configurationId = $configuration->getId();

        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement('DELETE FROM "user" WHERE id = ?', [$owner->getId()]);

        self::assertFalse($connection->fetchOne('SELECT id FROM configuration WHERE id = ?', [$configurationId]));
        self::assertFalse($connection->fetchOne('SELECT id FROM configuration_item WHERE configuration_id = ?', [$configurationId]));
    }

    private function item(ComponentSlot $slot, string $productId, int $quantity, string $unitPrice): ConfigurationItem
    {
        return (new ConfigurationItem())
            ->setSlot($slot)
            ->setProductId($productId)
            ->setQuantity($quantity)
            ->setProductNameSnapshot('Composant '.$slot->value)
            ->setProductSlugSnapshot('composant-'.$slot->value)
            ->setUnitPriceSnapshot($unitPrice);
    }
}
