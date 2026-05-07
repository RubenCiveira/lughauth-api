-- liquibase formatted sql

-- changeset auto.generated:20260507174311-1
CREATE TABLE access_user_invitation (
  uid          VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  email        VARCHAR(200)  NOT NULL,
  role_uid     VARCHAR(36)   NULL,
  invited_by   VARCHAR(36)   NOT NULL,
  token_hash   VARCHAR(64)   NOT NULL,
  metadata_json TEXT         NULL,
  status       ENUM('pending','accepted','cancelled','expired') NOT NULL DEFAULT 'pending',
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME      NOT NULL,
  accepted_at  DATETIME      NULL,
  accepted_by  VARCHAR(36)   NULL,
  CONSTRAINT PK_ACCESS_USER_INVITATION PRIMARY KEY (uid),
  UNIQUE KEY uk_invitation_token (token_hash),
  INDEX idx_invitation_email  (tenant_id, email),
  INDEX idx_invitation_status (tenant_id, status),
  INDEX idx_invitation_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
