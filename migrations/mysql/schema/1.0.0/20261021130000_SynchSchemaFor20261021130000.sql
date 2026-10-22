-- Shared anti-replay store of JWT identifiers (B.2, EP-07): request objects and completed authorization request references now; client assertions, DPoP proofs and JWT bearer grants later.
-- The primary key makes the INSERT the atomic check: a duplicate row is a replay.
CREATE TABLE _oauth_replay_jti (jti VARCHAR(255) NOT NULL, tenant_id VARCHAR(36) NOT NULL, client_id VARCHAR(250) NOT NULL, purpose ENUM('client_assertion', 'request_object', 'dpop', 'jwt_bearer', 'authorization_request') NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_REPLAY_JTI PRIMARY KEY (tenant_id, client_id, purpose, jti));
CREATE INDEX idx_replay_jti_expires ON _oauth_replay_jti(expires_at);
