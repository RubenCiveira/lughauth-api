-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_tenant_config ADD welcome_email LONGTEXT NULL;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_user ADD welcome_at timestamp DEFAULT null NULL;

