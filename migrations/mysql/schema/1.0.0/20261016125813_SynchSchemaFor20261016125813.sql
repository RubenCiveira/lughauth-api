-- liquibase formatted sql

-- changeset auto.generated:1825492372-2
CREATE INDEX FL_TRUSTED_CLIENT_CODE ON access_trusted_client(code);

-- changeset auto.generated:1825492372-3
CREATE TABLE access_dcr_initial_access_token (uid VARCHAR(255) NOT NULL, version INT NOT NULL, allowed_grant_types LONGTEXT NULL, `description` VARCHAR(255) NULL, expires_at timestamp NOT NULL, max_uses INT NOT NULL, revoked_at timestamp DEFAULT NULL NULL, token_hash VARCHAR(255) NOT NULL, uses INT NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_DCR_INITIAL_ACCESS_TOKEN PRIMARY KEY (uid), UNIQUE (token_hash));

-- changeset auto.generated:1825492372-4
CREATE TABLE access_trusted_client_allowed_audience (uid VARCHAR(255) NOT NULL, version INT NOT NULL, audience VARCHAR(255) NOT NULL, client VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT_ALLOWED_AUDIENCE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-5
ALTER TABLE access_tenant_login_provider ADD account_link_policy VARCHAR(255) NOT NULL;

-- changeset auto.generated:1825492372-6
ALTER TABLE access_tenant_config ADD acr_values_supported LONGTEXT NULL;

-- changeset auto.generated:1825492372-7
ALTER TABLE access_tenant_config ADD allow_password_grant BIT NOT NULL;

-- changeset auto.generated:1825492372-8
ALTER TABLE access_trusted_client ADD authorization_details_types LONGTEXT NULL;

-- changeset auto.generated:1825492372-9
ALTER TABLE access_trusted_client ADD authorization_signed_response_alg VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-10
ALTER TABLE access_tenant_config ADD dcr_allowed_auth_methods LONGTEXT NULL;

-- changeset auto.generated:1825492372-11
ALTER TABLE access_tenant_config ADD dcr_allowed_grant_types LONGTEXT NULL;

-- changeset auto.generated:1825492372-12
ALTER TABLE access_trusted_client ADD backchannel_client_notification_endpoint VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-13
ALTER TABLE access_tenant_login_provider ADD oidc_claim_mapping LONGTEXT NULL;

-- changeset auto.generated:1825492372-14
ALTER TABLE access_trusted_client ADD backchannel_token_delivery_mode VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-15
ALTER TABLE access_tenant_login_provider ADD oidc_scopes VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-16
ALTER TABLE access_trusted_client ADD default_acr_values VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-17
ALTER TABLE access_trusted_client ADD default_max_age INT DEFAULT null NULL;

-- changeset auto.generated:1825492372-18
ALTER TABLE access_trusted_client ADD dpop_bound_access_tokens BIT NOT NULL;

-- changeset auto.generated:1825492372-19
ALTER TABLE access_tenant_config ADD refresh_token_max_lifetime_seconds INT NOT NULL;

-- changeset auto.generated:1825492372-20
ALTER TABLE access_trusted_client ADD id_token_encrypted_response_alg VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-21
ALTER TABLE access_trusted_client ADD id_token_encrypted_response_enc VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-22
ALTER TABLE access_tenant_config ADD require_pushed_authorization_requests BIT NOT NULL;

-- changeset auto.generated:1825492372-23
ALTER TABLE access_trusted_client ADD id_token_signed_response_alg VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-24
ALTER TABLE access_trusted_client ADD require_pushed_authorization_requests BIT NOT NULL;

-- changeset auto.generated:1825492372-25
ALTER TABLE access_trusted_client ADD sector_identifier_uri VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-26
ALTER TABLE access_trusted_client ADD subject_type VARCHAR(255) NOT NULL;

-- changeset auto.generated:1825492372-27
ALTER TABLE access_trusted_client ADD tls_client_auth_subject_dn VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-28
ALTER TABLE access_trusted_client ADD tls_client_certificate_bound_access_tokens BIT NOT NULL;

-- changeset auto.generated:1825492372-29
ALTER TABLE access_trusted_client ADD token_endpoint_auth_signing_alg VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-30
ALTER TABLE access_trusted_client ADD userinfo_encrypted_response_alg VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-31
ALTER TABLE access_trusted_client ADD userinfo_encrypted_response_enc VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-32
ALTER TABLE access_trusted_client ADD userinfo_signed_response_alg VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-33
ALTER TABLE access_trusted_client ADD tenant VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-34
CREATE INDEX FL_DCR_INITIAL_ACCESS_TOKEN_DESCRIPTION ON access_dcr_initial_access_token(`description`);

-- changeset auto.generated:1825492372-35
CREATE INDEX FL_DCR_INITIAL_ACCESS_TOKEN_TENANT ON access_dcr_initial_access_token(tenant);

-- changeset auto.generated:1825492372-36
CREATE INDEX FL_TRUSTED_CLIENT_ALLOWED_AUDIENCE_CLIENTS ON access_trusted_client_allowed_audience(client);

-- changeset auto.generated:1825492372-37
CREATE INDEX FL_TRUSTED_CLIENT_TENANT ON access_trusted_client(tenant);

-- changeset auto.generated:1825492372-38
CREATE INDEX ST_DCR_INITIAL_ACCESS_TOKEN_DESCRIPTION_DESC ON access_dcr_initial_access_token(`description` DESC);

-- changeset auto.generated:1825492372-39
CREATE INDEX ST_TRUSTED_CLIENT_ALLOWED_AUDIENCE_AUDIENCE_ASC ON access_trusted_client_allowed_audience(audience);

-- changeset auto.generated:1825492372-40
CREATE INDEX ST_TRUSTED_CLIENT_ALLOWED_AUDIENCE_AUDIENCE_DESC ON access_trusted_client_allowed_audience(audience DESC);

-- changeset auto.generated:1825492372-41
CREATE INDEX ST_TRUSTED_CLIENT_CODE_DESC ON access_trusted_client(code DESC);

-- changeset auto.generated:1825492372-42
CREATE UNIQUE INDEX UK_TRUSTED_CLIENT_ALLOWED_AUDIENCE_CLIENT_AUDIENCE ON access_trusted_client_allowed_audience(client, audience);

-- changeset auto.generated:1825492372-43
CREATE UNIQUE INDEX UK_TRUSTED_CLIENT_TENANT_CODE ON access_trusted_client(tenant, code);

-- changeset auto.generated:1825492372-44
ALTER TABLE access_dcr_initial_access_token ADD CONSTRAINT FK_ACCESS_DCR_INITIAL_ACCESS_TOKEN_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-45
ALTER TABLE access_trusted_client_allowed_audience ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_ALLOWED_AUDIENCE_CLIENT FOREIGN KEY (client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-46
ALTER TABLE access_trusted_client ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

