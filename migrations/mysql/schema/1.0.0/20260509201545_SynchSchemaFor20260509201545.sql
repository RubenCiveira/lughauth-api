-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_tenant_config ADD magic_link_enabled BIT DEFAULT 0 NULL;

