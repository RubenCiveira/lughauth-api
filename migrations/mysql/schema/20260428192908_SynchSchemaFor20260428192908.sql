-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client ADD jwks_json LONGTEXT NULL;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_trusted_client ADD jwks_uri LONGTEXT NULL;

-- changeset auto.generated:1825492372-3
ALTER TABLE access_trusted_client ADD request_object_signing_alg VARCHAR(255) NULL;

