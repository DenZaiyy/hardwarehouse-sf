<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006133426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the customer email on the order to send the confirmation, filled from the account for existing orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD customer_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('UPDATE "order" SET customer_email = "user".email FROM "user" WHERE "order".user_id = "user".id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP customer_email');
    }
}
