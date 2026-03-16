<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315155421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix duplicate foreign key constraints with cascade deletion';
    }

    public function up(Schema $schema): void
    {
        // Drop all existing foreign key constraints (including duplicates)
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT IF EXISTS fk_9ce58ee18d9f6d38');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT IF EXISTS fk_fb34c6f78d9f6d38');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT IF EXISTS fk_fb34c6ca8d9f6d38');
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT IF EXISTS fk_2cb20dc8d9f6d38');

        // Recreate clean foreign key constraints with CASCADE deletion
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT fk_order_line_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_address ADD CONSTRAINT fk_order_address_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_shipment_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop CASCADE foreign key constraints
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT fk_order_line_order');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT fk_order_address_order');
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT fk_shipment_order');

        // Recreate original foreign key constraints without CASCADE
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT fk_9ce58ee18d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id)');
        $this->addSql('ALTER TABLE order_address ADD CONSTRAINT fk_fb34c6f78d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id)');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_2cb20dc8d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id)');
    }
}
