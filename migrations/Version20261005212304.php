<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005212304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'One rating per customer and product, instead of a single rating per product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_identifier_productid');
        $this->addSql('CREATE UNIQUE INDEX uniq_rating_user_product ON rating (user_id, product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_rating_user_product');
        $this->addSql('CREATE UNIQUE INDEX uniq_identifier_productid ON rating (product_id)');
    }
}
