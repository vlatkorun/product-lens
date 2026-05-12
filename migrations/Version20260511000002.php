<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and users_tenants tables with role and status PostgreSQL enums';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql("CREATE TYPE user_role AS ENUM ('super_admin', 'admin', 'tenant_admin', 'tenant')");
        $this->addSql("CREATE TYPE user_status AS ENUM ('active', 'inactive')");

        $this->addSql(<<<'SQL'
                CREATE TABLE users (
                    id          BIGINT       GENERATED ALWAYS AS IDENTITY NOT NULL,
                    resource_id UUID         NOT NULL,
                    email       VARCHAR(255) NOT NULL,
                    password   VARCHAR(255) NOT NULL,
                    role       user_role    NOT NULL,
                    status     user_status  NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);

        $this->addSql('ALTER TABLE users ADD CONSTRAINT users_resource_id_uq UNIQUE (resource_id)');
        $this->addSql('CREATE UNIQUE INDEX users_email_uq ON users (email)');
        $this->addSql('CREATE INDEX users_role_idx ON users (role)');
        $this->addSql('CREATE INDEX users_status_idx ON users (status)');

        $this->addSql(<<<'SQL'
                CREATE TABLE users_tenants (
                    user_id   UUID NOT NULL,
                    tenant_id UUID NOT NULL,
                    PRIMARY KEY (user_id, tenant_id),
                    CONSTRAINT fk_users_tenants_user_id   FOREIGN KEY (user_id)   REFERENCES users   (resource_id) ON DELETE CASCADE,
                    CONSTRAINT fk_users_tenants_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants (resource_id) ON DELETE CASCADE
                )
            SQL);

        $this->addSql('CREATE INDEX users_tenants_tenant_id_idx ON users_tenants (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users_tenants');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TYPE user_role');
        $this->addSql('DROP TYPE user_status');
    }
}
