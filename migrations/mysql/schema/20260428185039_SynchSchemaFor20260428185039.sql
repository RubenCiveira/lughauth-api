-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client MODIFY token_endpoint_auth_method VARCHAR(255) NOT NULL;

-- changeset auto.generated:1825492372-3
ALTER TABLE access_trusted_client ADD allowed_scopes_m_2m LONGTEXT NULL;

-- changeset auto.generated:1825492372-4
ALTER TABLE access_trusted_client ADD m_2m_token_ttl_seconds INT NOT NULL;

