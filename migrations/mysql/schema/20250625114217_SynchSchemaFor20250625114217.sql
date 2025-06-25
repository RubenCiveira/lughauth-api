--liquibase formatted sql

--changeset auto.generated:1825492372-1
ALTER TABLE access_tenant_terms_of_use ADD enabled BIT(1) NOT NULL;

