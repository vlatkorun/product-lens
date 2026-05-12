<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenants table with encrypted credential columns and JSONB fields';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql("CREATE TYPE tenant_status AS ENUM ('active', 'suspended', 'uninstalled')");

        $this->addSql(<<<'SQL'
                CREATE TABLE tenants (
                    id                     BIGINT       GENERATED ALWAYS AS IDENTITY NOT NULL,
                    resource_id            UUID         NOT NULL,
                    name                   VARCHAR(255) NOT NULL,
                    shop_handle            VARCHAR(255) NOT NULL,
                    shop_domain            VARCHAR(255) NOT NULL,
                    email                  VARCHAR(255) DEFAULT NULL,
                    currency_code          VARCHAR(3)   DEFAULT NULL,
                    country_code           VARCHAR(2)   DEFAULT NULL,
                    timezone               VARCHAR(100) DEFAULT NULL,
                    shopify_plan           VARCHAR(100) DEFAULT NULL,
                    shopify_scope          TEXT         DEFAULT NULL,
                    status                 tenant_status NOT NULL DEFAULT 'active',
                    installed_at           TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    uninstalled_at         TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    created_at             TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at             TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    shopify_access_token   TEXT         DEFAULT NULL,
                    shopify_webhook_secret TEXT         DEFAULT NULL,
                    configuration          JSONB        NOT NULL DEFAULT '{}',
                    feature_flags          JSONB        NOT NULL DEFAULT '[]',
                    PRIMARY KEY (id)
                )
            SQL);

        $this->addSql("ALTER TABLE tenants ADD CONSTRAINT chk_tenants_shop_domain CHECK (shop_domain LIKE '%.myshopify.com')");
        $this->addSql('ALTER TABLE tenants ADD CONSTRAINT tenants_resource_id_uq UNIQUE (resource_id)');
        $this->addSql('CREATE UNIQUE INDEX tenants_shop_domain_uq ON tenants (shop_domain)');
        $this->addSql('CREATE UNIQUE INDEX tenants_shop_handle_uq ON tenants (shop_handle)');
        $this->addSql('CREATE INDEX tenants_status_idx ON tenants (status)');
        $this->addSql('CREATE INDEX tenants_installed_at_idx ON tenants (installed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenants');
        $this->addSql('DROP TYPE tenant_status');
    }
}
