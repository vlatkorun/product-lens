<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable Row Level Security on CatalogSync tables and create app_scheduler role';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        foreach (['tenant_monitored_collections', 'tenant_monitored_collections_sync', 'products'] as $table) {
            $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf(
                "CREATE POLICY tenant_isolation ON %s USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)",
                $table,
            ));
        }

        $this->addSql('CREATE ROLE app_scheduler WITH NOLOGIN BYPASSRLS');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP ROLE IF EXISTS app_scheduler');

        foreach (['tenant_monitored_collections', 'tenant_monitored_collections_sync', 'products'] as $table) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation ON %s', $table));
            $this->addSql(\sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }
    }
}
