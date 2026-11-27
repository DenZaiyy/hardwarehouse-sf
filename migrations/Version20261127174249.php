<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127174249 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep on each order the name of the carrier chosen in the checkout';
    }

    public function up(Schema $schema): void
    {
        // Nullable : les commandes passées avant ne connaissent que le prix de leur livraison
        $this->addSql('ALTER TABLE "order" ADD carrier_name_snapshot VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP carrier_name_snapshot');
    }
}
