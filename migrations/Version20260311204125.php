<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311204125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT fk_9ce58ee1fcdaeaaa');
        $this->addSql('DROP INDEX idx_9ce58ee1fcdaeaaa');
        $this->addSql('ALTER TABLE order_line ADD product_slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE order_line ADD product_name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE order_line ADD tax_rate NUMERIC(5, 2) NOT NULL');
        $this->addSql('ALTER TABLE order_line ADD line_total NUMERIC(15, 2) NOT NULL');
        $this->addSql('ALTER TABLE order_line ALTER unit_price TYPE NUMERIC(15, 2)');
        $this->addSql('ALTER TABLE order_line RENAME COLUMN order_id_id TO order_id');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE18D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_9CE58EE18D9F6D38 ON order_line (order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT FK_9CE58EE18D9F6D38');
        $this->addSql('DROP INDEX IDX_9CE58EE18D9F6D38');
        $this->addSql('ALTER TABLE order_line DROP product_slug');
        $this->addSql('ALTER TABLE order_line DROP product_name');
        $this->addSql('ALTER TABLE order_line DROP tax_rate');
        $this->addSql('ALTER TABLE order_line DROP line_total');
        $this->addSql('ALTER TABLE order_line ALTER unit_price TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE order_line RENAME COLUMN order_id TO order_id_id');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT fk_9ce58ee1fcdaeaaa FOREIGN KEY (order_id_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_9ce58ee1fcdaeaaa ON order_line (order_id_id)');
    }
}
