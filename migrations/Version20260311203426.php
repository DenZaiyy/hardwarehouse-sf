<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311203426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shipment ADD orderr_id INT NOT NULL');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT FK_2CB20DC7742FDB3 FOREIGN KEY (orderr_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2CB20DC7742FDB3 ON shipment (orderr_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT FK_2CB20DC7742FDB3');
        $this->addSql('DROP INDEX IDX_2CB20DC7742FDB3');
        $this->addSql('ALTER TABLE shipment DROP orderr_id');
    }
}
