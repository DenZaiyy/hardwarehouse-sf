<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005220322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the United Kingdom with its ISO 3166 code GB instead of EN';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE address SET country = 'GB' WHERE country = 'EN'");
        $this->addSql("UPDATE order_address SET country = 'GB' WHERE country = 'EN'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE address SET country = 'EN' WHERE country = 'GB'");
        $this->addSql("UPDATE order_address SET country = 'EN' WHERE country = 'GB'");
    }
}
