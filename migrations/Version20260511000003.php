<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CatalogSync tables: collection_sync_configs, sync_jobs, products';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql("CREATE TYPE sync_status    AS ENUM ('pending', 'running', 'completed', 'failed')");
        $this->addSql("CREATE TYPE product_status AS ENUM ('active', 'archived', 'draft')");

        $this->addSql(<<<'SQL'
                CREATE TABLE collection_sync_configs (
                    id              UUID         NOT NULL,
                    tenant_id       UUID         NOT NULL,
                    collection_gid  VARCHAR(255) NOT NULL,
                    collection_name VARCHAR(255) NOT NULL,
                    feature_flags   JSONB        NOT NULL DEFAULT '[]',
                    enabled         BOOLEAN      NOT NULL DEFAULT true,
                    created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    PRIMARY KEY (id),
                    CONSTRAINT fk_collection_sync_configs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                )
            SQL);

        $this->addSql('CREATE UNIQUE INDEX collection_sync_configs_tenant_collection_uq ON collection_sync_configs (tenant_id, collection_gid)');
        $this->addSql('CREATE INDEX collection_sync_configs_tenant_id_idx ON collection_sync_configs (tenant_id)');
        $this->addSql('CREATE INDEX collection_sync_configs_enabled_idx ON collection_sync_configs (tenant_id) WHERE enabled = true');

        $this->addSql(<<<'SQL'
                CREATE TABLE sync_jobs (
                    id                      UUID         NOT NULL,
                    tenant_id               UUID         NOT NULL,
                    monitored_collection_id UUID         NOT NULL,
                    collection_gid          VARCHAR(255) NOT NULL,
                    status                  sync_status  NOT NULL DEFAULT 'pending',
                    cursor                  JSONB        DEFAULT NULL,
                    total_processed         INTEGER      NOT NULL DEFAULT 0,
                    started_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    completed_at            TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    failed_at               TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    failure_reason          TEXT         DEFAULT NULL,
                    PRIMARY KEY (id),
                    CONSTRAINT fk_sync_jobs_tenant               FOREIGN KEY (tenant_id)               REFERENCES tenants               (id) ON DELETE CASCADE,
                    CONSTRAINT fk_sync_jobs_monitored_collection FOREIGN KEY (monitored_collection_id) REFERENCES collection_sync_configs (id) ON DELETE CASCADE
                )
            SQL);

        $this->addSql('CREATE INDEX sync_jobs_tenant_id_idx ON sync_jobs (tenant_id)');
        $this->addSql('CREATE INDEX sync_jobs_monitored_collection_id_idx ON sync_jobs (monitored_collection_id)');
        $this->addSql('CREATE INDEX sync_jobs_status_idx ON sync_jobs (status)');
        $this->addSql('CREATE INDEX sync_jobs_started_at_idx ON sync_jobs (started_at)');
        $this->addSql("CREATE INDEX sync_jobs_running_idx ON sync_jobs (monitored_collection_id) WHERE status IN ('pending', 'running')");

        $this->addSql(<<<'SQL'
                CREATE TABLE products (
                    id                 UUID           NOT NULL,
                    shopify_gid        VARCHAR(255)   NOT NULL,
                    tenant_id          UUID           NOT NULL,
                    collection_gid     VARCHAR(255)   NOT NULL,
                    title              VARCHAR(255)   NOT NULL,
                    handle             VARCHAR(255)   NOT NULL,
                    vendor             VARCHAR(255)   NOT NULL DEFAULT '',
                    product_type       VARCHAR(255)   NOT NULL DEFAULT '',
                    status             product_status NOT NULL,
                    images             JSONB          NOT NULL DEFAULT '[]',
                    featured_image_url TEXT           DEFAULT NULL,
                    synced_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    PRIMARY KEY (id),
                    CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
                )
            SQL);

        $this->addSql('CREATE UNIQUE INDEX products_tenant_shopify_gid_uq ON products (tenant_id, shopify_gid)');
        $this->addSql('CREATE INDEX products_tenant_id_idx ON products (tenant_id)');
        $this->addSql('CREATE INDEX products_collection_gid_idx ON products (tenant_id, collection_gid)');
        $this->addSql('CREATE INDEX products_status_idx ON products (tenant_id, status)');
        $this->addSql('CREATE INDEX products_synced_at_idx ON products (synced_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE sync_jobs');
        $this->addSql('DROP TABLE collection_sync_configs');
        $this->addSql('DROP TYPE sync_status');
        $this->addSql('DROP TYPE product_status');
    }
}
