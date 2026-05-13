<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add variants JSONB column to products table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE products ADD COLUMN variants JSONB NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP COLUMN variants');
    }
}
