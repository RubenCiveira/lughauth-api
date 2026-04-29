ALTER TABLE access_tenant_config
  ADD COLUMN IF NOT EXISTS webauthn_enabled    TINYINT(1)    NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS webauthn_rp_id      VARCHAR(200)  NULL,
  ADD COLUMN IF NOT EXISTS webauthn_rp_name    VARCHAR(200)  NULL;

CREATE TABLE IF NOT EXISTS access_user_webauthn_credential (
  uid              VARCHAR(36)   NOT NULL,
  tenant_id        VARCHAR(36)   NOT NULL,
  user_uid         VARCHAR(36)   NOT NULL,
  username         VARCHAR(200)  NOT NULL,
  credential_id    VARCHAR(255)  NOT NULL,
  public_key       TEXT          NOT NULL,
  sign_count       BIGINT        NOT NULL DEFAULT 0,
  aaguid           VARCHAR(36)   NULL,
  device_name      VARCHAR(200)  NULL,
  transports_json  TEXT          NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at     DATETIME      NULL,
  enabled          TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_credential_tenant (credential_id, tenant_id),
  INDEX idx_webauthn_user (user_uid, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS _oauth_webauthn_challenge (
  challenge_id          VARCHAR(36)   NOT NULL,
  tenant_id             VARCHAR(36)   NOT NULL,
  user_uid              VARCHAR(36)   NULL,
  challenge             VARCHAR(100)  NOT NULL,
  type                  ENUM('register','authenticate') NOT NULL,
  options_json          TEXT          NULL,
  created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at            DATETIME      NOT NULL,
  verified              TINYINT(1)    NOT NULL DEFAULT 0,
  verified_at           DATETIME      NULL,
  verified_user_uid     VARCHAR(36)   NULL,
  verified_username     VARCHAR(200)  NULL,
  PRIMARY KEY (challenge_id),
  INDEX idx_challenge_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
