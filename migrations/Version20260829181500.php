<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829181500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a unique constraint on user.user_name (no duplicate usernames verified beforehand)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_USERNAME ON "user" (user_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_USERNAME');
    }
}
