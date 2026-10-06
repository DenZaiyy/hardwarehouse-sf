<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006133958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Number invoices continuously per year';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice_counter (year INT NOT NULL, last_number INT NOT NULL, PRIMARY KEY (year))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invoice_counter');
    }
}
