<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311212248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT fk_2cb20dc7742fdb3');
        $this->addSql('DROP INDEX idx_2cb20dc7742fdb3');
        $this->addSql('ALTER TABLE shipment RENAME COLUMN orderr_id TO order_id');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT FK_2CB20DC8D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2CB20DC8D9F6D38 ON shipment (order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT FK_2CB20DC8D9F6D38');
        $this->addSql('DROP INDEX IDX_2CB20DC8D9F6D38');
        $this->addSql('ALTER TABLE shipment RENAME COLUMN order_id TO orderr_id');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_2cb20dc7742fdb3 FOREIGN KEY (orderr_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2cb20dc7742fdb3 ON shipment (orderr_id)');
    }
}
