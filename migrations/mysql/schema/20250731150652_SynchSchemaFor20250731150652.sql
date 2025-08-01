--liquibase formatted sql

--changeset auto.generated:1825492372-1
ALTER TABLE access_user ADD approve VARCHAR(255) NULL;

--changeset auto.generated:1825492372-2
update access_user SET approve='ACCEPTED';