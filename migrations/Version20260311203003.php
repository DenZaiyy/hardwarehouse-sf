<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311203003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT fk_f52993989d86650f');
        $this->addSql('DROP INDEX idx_f52993989d86650f');
        $this->addSql('ALTER TABLE "order" ADD reference VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD subtotal NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD shipping_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD discount_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD tax_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD total_amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD currency VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE "order" DROP delivery_address');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN user_full_name TO user_full_name_snapshot');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN user_id_id TO user_id');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id)');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON "order" (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398A76ED395');
        $this->addSql('DROP INDEX IDX_F5299398A76ED395');
        $this->addSql('ALTER TABLE "order" ADD delivery_address TEXT NOT NULL');
        $this->addSql('ALTER TABLE "order" DROP reference');
        $this->addSql('ALTER TABLE "order" DROP subtotal');
        $this->addSql('ALTER TABLE "order" DROP shipping_amount');
        $this->addSql('ALTER TABLE "order" DROP discount_amount');
        $this->addSql('ALTER TABLE "order" DROP tax_amount');
        $this->addSql('ALTER TABLE "order" DROP total_amount');
        $this->addSql('ALTER TABLE "order" DROP currency');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN user_full_name_snapshot TO user_full_name');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN user_id TO user_id_id');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT fk_f52993989d86650f FOREIGN KEY (user_id_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_f52993989d86650f ON "order" (user_id_id)');
    }
}
