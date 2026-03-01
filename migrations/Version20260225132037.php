<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225132037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating ADD usr_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622C69D3FB FOREIGN KEY (usr_id) REFERENCES "user" (id)');
        $this->addSql('CREATE INDEX IDX_D8892622C69D3FB ON rating (usr_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rating DROP CONSTRAINT FK_D8892622C69D3FB');
        $this->addSql('DROP INDEX IDX_D8892622C69D3FB');
        $this->addSql('ALTER TABLE rating DROP usr_id');
    }
}
