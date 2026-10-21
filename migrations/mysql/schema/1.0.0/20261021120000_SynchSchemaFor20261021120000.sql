-- Public session identifier published as the sid claim, never the cookie value (B.1, EP-05).
-- Tenant of the session and the authentication methods actually used (amr, RFC 8176).
ALTER TABLE _oauth_session ADD COLUMN sid VARCHAR(64) NULL, ADD COLUMN tenant_id VARCHAR(36) NULL, ADD COLUMN amr VARCHAR(128) NULL;
-- Live sessions get a random sid unrelated to their cookie.
UPDATE _oauth_session SET sid = SHA2(CONCAT(UUID(), RAND()), 256) WHERE sid IS NULL;
UPDATE _oauth_session s JOIN access_tenant t ON s.issuer LIKE CONCAT('%/openid/', t.name) SET s.tenant_id = t.uid WHERE s.tenant_id IS NULL;
ALTER TABLE _oauth_session MODIFY sid VARCHAR(64) NOT NULL;
CREATE UNIQUE INDEX UX__OAUTH_SESSION_SID ON _oauth_session(sid);
CREATE INDEX IX__OAUTH_SESSION_USER_TENANT ON _oauth_session(user_uid, tenant_id);
