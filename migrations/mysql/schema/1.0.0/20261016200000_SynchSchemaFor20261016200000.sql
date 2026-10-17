-- Revoked refresh token families (replay detection), kept until the family absolute expiration.
CREATE TABLE _oauth_revoked_refresh_family (family_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, revoked_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_REVOKED_REFRESH_FAMILY PRIMARY KEY (family_id));

CREATE INDEX idx_revoked_family_expires ON _oauth_revoked_refresh_family(expires_at);
