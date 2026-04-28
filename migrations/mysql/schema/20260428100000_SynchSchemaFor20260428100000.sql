-- liquibase formatted sql

-- changeset par.implementation:20260428100000-1
CREATE TABLE _oauth_par_request (
  request_uri  VARCHAR(100) NOT NULL,
  tenant_id    VARCHAR(36)  NOT NULL,
  client_id    VARCHAR(36)  NOT NULL,
  params_json  TEXT         NOT NULL,  -- JSON con todos los params
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME     NOT NULL,
  used_at      DATETIME     NULL,      -- solo se puede usar una vez
  PRIMARY KEY (request_uri),
  INDEX idx_par_tenant  (tenant_id),
  INDEX idx_par_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

