--liquibase formatted sql

--changeset auto.generated:1825492372-1
ALTER TABLE access_user ADD approved BIT(1) DEFAULT 0 NULL;

--changeset auto.generated:1825492372-2
ALTER TABLE access_user ADD rejected BIT(1) DEFAULT 0 NULL;

