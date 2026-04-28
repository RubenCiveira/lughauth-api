-- liquibase formatted sql

-- changeset dcr.implementation:20260428110000-1
ALTER TABLE access_trusted_client
  ADD COLUMN registration_access_token_hash VARCHAR(64) NULL,
  ADD COLUMN client_name                    VARCHAR(200) NULL,
  ADD COLUMN logo_uri                       VARCHAR(500) NULL,
  ADD COLUMN client_uri                     VARCHAR(500) NULL,
  ADD COLUMN policy_uri                     VARCHAR(500) NULL,
  ADD COLUMN tos_uri                        VARCHAR(500) NULL,
  ADD COLUMN token_endpoint_auth_method     VARCHAR(50) NOT NULL DEFAULT 'client_secret_basic',
  ADD COLUMN grant_types_json               TEXT NULL,
  ADD COLUMN response_types_json            TEXT NULL,
  ADD COLUMN dynamically_registered         TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN registered_at                  DATETIME NULL;

-- changeset dcr.implementation:20260428110000-2
ALTER TABLE access_tenant_config
  ADD COLUMN dynamic_registration_policy ENUM('open','token_required','disabled') NOT NULL DEFAULT 'disabled';
