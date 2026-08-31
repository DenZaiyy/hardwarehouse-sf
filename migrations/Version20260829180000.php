<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe payment intent / last event tracking to Order, for reliable webhook order resolution (no more "most recent order" fallback)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD stripe_payment_intent_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD last_stripe_event_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDER_STRIPE_PAYMENT_INTENT_ID ON "order" (stripe_payment_intent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_ORDER_STRIPE_PAYMENT_INTENT_ID');
        $this->addSql('ALTER TABLE "order" DROP stripe_payment_intent_id');
        $this->addSql('ALTER TABLE "order" DROP last_stripe_event_id');
    }
}
