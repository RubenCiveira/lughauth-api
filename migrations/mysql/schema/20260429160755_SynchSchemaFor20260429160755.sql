-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_tenant_login_provider ADD saml_idp_entity_id VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_tenant_login_provider ADD saml_idp_idp_cert VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-3
ALTER TABLE access_tenant_login_provider ADD saml_idp_metadata_url VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-4
ALTER TABLE access_tenant_login_provider ADD saml_idp_sso_url VARCHAR(255) NULL;

