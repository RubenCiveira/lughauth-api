--liquibase formatted sql

--changeset auto.generated:1825492372-1
ALTER TABLE access_user_access_temporal_code ADD register_code_expiration timestamp DEFAULT null NULL;

--changeset auto.generated:1825492372-2
ALTER TABLE access_user_access_temporal_code ADD register_code_url VARCHAR(255) NULL;

