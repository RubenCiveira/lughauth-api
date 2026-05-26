-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE _oauth_audit_log (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, event_type VARCHAR(64) NOT NULL, user_id VARCHAR(36) NULL, client_id VARCHAR(36) NULL, session_id VARCHAR(255) NULL, jti VARCHAR(36) NULL, grant_type VARCHAR(64) NULL, scope TEXT NULL, acr VARCHAR(8) NULL, ip_address VARCHAR(64) NULL, user_agent VARCHAR(512) NULL, failure_reason VARCHAR(64) NULL, payload_json MEDIUMTEXT NULL, created_at datetime NOT NULL, CONSTRAINT PK__OAUTH_AUDIT_LOG PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-2
CREATE TABLE _oauth_delegated_state (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, state_token VARCHAR(128) NOT NULL, provider_id VARCHAR(36) NOT NULL, session_context_json MEDIUMTEXT NOT NULL, created_at datetime NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_DELEGATED_STATE PRIMARY KEY (uid), UNIQUE (state_token));

-- changeset auto.generated:1825492372-3
CREATE TABLE _oauth_session_grant (id VARCHAR(36) NOT NULL, session VARCHAR(255) NOT NULL, client_id VARCHAR(250) NOT NULL, grant_type VARCHAR(50) NOT NULL, scope TEXT NULL, audiences TEXT NOT NULL, auth_data TEXT NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, revoked_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_SESSION_GRANT PRIMARY KEY (id));

-- changeset auto.generated:1825492372-4
CREATE TABLE _oauth_session_token (id VARCHAR(36) NOT NULL, session VARCHAR(255) NOT NULL, jti VARCHAR(36) NOT NULL, refresh_jti VARCHAR(36) NOT NULL, issued_at datetime NOT NULL, expires_at datetime NOT NULL, revoked_at datetime DEFAULT NULL NULL, grant_id VARCHAR(36) NULL, client_id VARCHAR(250) NULL, scope TEXT NULL, audiences TEXT NULL, auth_data TEXT NULL, CONSTRAINT PK__OAUTH_SESSION_TOKEN PRIMARY KEY (id), UNIQUE (jti), UNIQUE (refresh_jti));

-- changeset auto.generated:1825492372-5
ALTER TABLE _oauth_session ADD user_uid VARCHAR(36) NOT NULL;

-- changeset auto.generated:1825492372-6
ALTER TABLE _oauth_session ADD auth_time datetime DEFAULT null NULL;

-- changeset auto.generated:1825492372-7
ALTER TABLE _oauth_session ADD acr TINYINT DEFAULT 0 NOT NULL;

-- changeset auto.generated:1825492372-8
ALTER TABLE _oauth_session ADD sso_clients_json MEDIUMTEXT NULL;

-- changeset auto.generated:1825492372-9
ALTER TABLE _oauth_session ADD PRIMARY KEY (session);

-- changeset auto.generated:1825492372-10
CREATE INDEX FK__OAUTH_SESSION_GRANT_SESSION ON _oauth_session_grant(session);

-- changeset auto.generated:1825492372-11
CREATE INDEX FK__OAUTH_SESSION_TOKEN_GRANT ON _oauth_session_token(grant_id);

-- changeset auto.generated:1825492372-12
CREATE INDEX idx_oauth_audit_created ON _oauth_audit_log(created_at);

-- changeset auto.generated:1825492372-13
CREATE INDEX idx_oauth_audit_session ON _oauth_audit_log(session_id);

-- changeset auto.generated:1825492372-14
CREATE INDEX idx_oauth_audit_type ON _oauth_audit_log(event_type, tenant_id, created_at);

-- changeset auto.generated:1825492372-15
CREATE INDEX idx_oauth_audit_user ON _oauth_audit_log(user_id, tenant_id, created_at);

-- changeset auto.generated:1825492372-16
CREATE INDEX idx_oauth_delegated_state_expires ON _oauth_delegated_state(expires_at);

-- changeset auto.generated:1825492372-17
CREATE INDEX idx_oauth_session_expiration ON _oauth_session(expiration);

-- changeset auto.generated:1825492372-18
CREATE INDEX idx_oauth_session_expiration ON _oauth_temporal_codes(expiration);

-- changeset auto.generated:1825492372-19
CREATE INDEX idx_oauth_session_user_uid ON _oauth_magic_link(user_uid);

-- changeset auto.generated:1825492372-20
CREATE INDEX idx_session_token_exp ON _oauth_session_token(expires_at);

-- changeset auto.generated:1825492372-21
CREATE INDEX idx_session_token_session ON _oauth_session_token(session);

-- changeset auto.generated:1825492372-22
ALTER TABLE _oauth_session_grant ADD CONSTRAINT FK__OAUTH_SESSION_GRANT_SESSION FOREIGN KEY (session) REFERENCES _oauth_session (session) ON UPDATE CASCADE ON DELETE CASCADE;

-- changeset auto.generated:1825492372-23
ALTER TABLE _oauth_session_token ADD CONSTRAINT FK__OAUTH_SESSION_TOKEN_GRANT FOREIGN KEY (grant_id) REFERENCES _oauth_session_grant (id) ON UPDATE RESTRICT ON DELETE SET NULL;

-- changeset auto.generated:1825492372-24
ALTER TABLE _oauth_session_token ADD CONSTRAINT FK__OAUTH_SESSION_TOKEN_SESSION FOREIGN KEY (session) REFERENCES _oauth_session (session) ON UPDATE CASCADE ON DELETE CASCADE;

