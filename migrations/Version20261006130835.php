<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006130835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the payment method of each order; orders placed before PayPal were all paid by card';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD payment_method VARCHAR(20) DEFAULT NULL');
        $this->addSql("UPDATE \"order\" SET payment_method = 'card'");
        $this->addSql('ALTER TABLE "order" ALTER payment_method SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP payment_method');
    }
}
