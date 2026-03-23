<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260323211956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_line ADD discount_price_snapshot NUMERIC(15, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE cart_line ADD discount_amount_snapshot NUMERIC(15, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT fk_order_address_order');
        $this->addSql('ALTER TABLE order_address ADD CONSTRAINT FK_FB34C6CA8D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT fk_order_line_order');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE18D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT fk_shipment_order');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT FK_2CB20DC8D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_line DROP discount_price_snapshot');
        $this->addSql('ALTER TABLE cart_line DROP discount_amount_snapshot');
        $this->addSql('ALTER TABLE order_address DROP CONSTRAINT FK_FB34C6CA8D9F6D38');
        $this->addSql('ALTER TABLE order_address ADD CONSTRAINT fk_order_address_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT FK_9CE58EE18D9F6D38');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT fk_order_line_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shipment DROP CONSTRAINT FK_2CB20DC8D9F6D38');
        $this->addSql('ALTER TABLE shipment ADD CONSTRAINT fk_shipment_order FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
