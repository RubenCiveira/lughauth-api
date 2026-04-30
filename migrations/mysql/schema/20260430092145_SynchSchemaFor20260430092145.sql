-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_consent_purpose MODIFY activation_date timestamp NOT NULL;

