<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311211756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT fk_9065174492fcea00');
        $this->addSql('DROP INDEX uniq_9065174492fcea00');
        $this->addSql('ALTER TABLE invoice RENAME COLUMN invoice TO file_path');
        $this->addSql('ALTER TABLE invoice RENAME COLUMN ordr_id TO order_id');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517448D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_906517448D9F6D38 ON invoice (order_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_REFERENCE_INVOICE ON invoice (reference)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_906517448D9F6D38');
        $this->addSql('DROP INDEX UNIQ_906517448D9F6D38');
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_REFERENCE_INVOICE');
        $this->addSql('ALTER TABLE invoice RENAME COLUMN file_path TO invoice');
        $this->addSql('ALTER TABLE invoice RENAME COLUMN order_id TO ordr_id');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT fk_9065174492fcea00 FOREIGN KEY (ordr_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_9065174492fcea00 ON invoice (ordr_id)');
    }
}
