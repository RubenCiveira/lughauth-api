--liquibase formatted sql

--changeset auto.generated:1825492372-2
ALTER TABLE access_scope_attribute_permission ADD permision VARCHAR(255) NOT NULL;

--changeset auto.generated:1825492372-3
ALTER TABLE access_security_domain ADD modifica_all_attributes BIT(1) DEFAULT 0 NULL;

--changeset auto.generated:1825492372-4
ALTER TABLE access_security_domain ADD view_all_attributes BIT(1) DEFAULT 0 NULL;

