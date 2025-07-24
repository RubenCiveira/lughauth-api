--liquibase formatted sql

--changeset auto.generated:1825492372-2
ALTER TABLE access_security_domain ADD modify_all_attributes BIT(1) DEFAULT 0 NULL;

