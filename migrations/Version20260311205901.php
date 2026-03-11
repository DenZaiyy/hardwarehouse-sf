<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311205901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address DROP CONSTRAINT fk_d4e6f81586dff2');
        $this->addSql('DROP INDEX idx_d4e6f81586dff2');
        $this->addSql('ALTER TABLE address ADD first_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE address ADD last_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE address DROP firstname');
        $this->addSql('ALTER TABLE address DROP lastname');
        $this->addSql('ALTER TABLE address RENAME COLUMN cp TO postal_code');
        $this->addSql('ALTER TABLE address RENAME COLUMN user_info_id TO user_id');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT FK_D4E6F81A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_D4E6F81A76ED395 ON address (user_id)');
        $this->addSql('ALTER TABLE rating DROP CONSTRAINT fk_d8892622c69d3fb');
        $this->addSql('DROP INDEX idx_d8892622c69d3fb');
        $this->addSql('ALTER TABLE rating RENAME COLUMN usr_id TO user_id');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id)');
        $this->addSql('CREATE INDEX IDX_D8892622A76ED395 ON rating (user_id)');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN username TO user_name');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE address DROP CONSTRAINT FK_D4E6F81A76ED395');
        $this->addSql('DROP INDEX IDX_D4E6F81A76ED395');
        $this->addSql('ALTER TABLE address ADD firstname VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE address ADD lastname VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE address DROP first_name');
        $this->addSql('ALTER TABLE address DROP last_name');
        $this->addSql('ALTER TABLE address RENAME COLUMN postal_code TO cp');
        $this->addSql('ALTER TABLE address RENAME COLUMN user_id TO user_info_id');
        $this->addSql('ALTER TABLE address ADD CONSTRAINT fk_d4e6f81586dff2 FOREIGN KEY (user_info_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d4e6f81586dff2 ON address (user_info_id)');
        $this->addSql('ALTER TABLE rating DROP CONSTRAINT FK_D8892622A76ED395');
        $this->addSql('DROP INDEX IDX_D8892622A76ED395');
        $this->addSql('ALTER TABLE rating RENAME COLUMN user_id TO usr_id');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT fk_d8892622c69d3fb FOREIGN KEY (usr_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d8892622c69d3fb ON rating (usr_id)');
        $this->addSql('ALTER TABLE "user" RENAME COLUMN user_name TO username');
    }
}
