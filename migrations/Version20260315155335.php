<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315155335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cascade deletion to order foreign keys';
    }

    public function up(Schema $schema): void
    {
        // Drop existing foreign key constraints
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT IF EXISTS fk_9ce58ee18d9f6d38');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT IF EXISTS fk_fb34c6f78d9f6d38');
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT IF EXISTS fk_2cb20dc8d9f6d38');

        // Recreate foreign key constraints with CASCADE deletion
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT fk_9ce58ee18d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_address ADD CONSTRAINT fk_fb34c6f78d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_2cb20dc8d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop CASCADE foreign key constraints
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT fk_9ce58ee18d9f6d38');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT fk_fb34c6f78d9f6d38');
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT fk_2cb20dc8d9f6d38');

        // Recreate original foreign key constraints without CASCADE
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT fk_9ce58ee18d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id)');
        $this->addSql('ALTER TABLE order_address ADD CONSTRAINT fk_fb34c6f78d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id)');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_2cb20dc8d9f6d38 FOREIGN KEY (order_id) REFERENCES "order" (id)');
    }
}
