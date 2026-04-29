-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE _oauth_magic_link (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_uid VARCHAR(36) NOT NULL, client_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, redirect_uri VARCHAR(500) NOT NULL, scope VARCHAR(500) DEFAULT 'openid email' NOT NULL, state VARCHAR(500) NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, used_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_MAGIC_LINK PRIMARY KEY (uid), UNIQUE (token_hash));

-- changeset auto.generated:1825492372-2
CREATE TABLE _oauth_webauthn_challenge (challenge_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_uid VARCHAR(36) NULL, challenge VARCHAR(100) NOT NULL, type ENUM('register', 'authenticate') NOT NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_WEBAUTHN_CHALLENGE PRIMARY KEY (challenge_id));

-- changeset auto.generated:1825492372-3
CREATE TABLE access_user_webauthn_credential (uid VARCHAR(255) NOT NULL, version INT NOT NULL, aaguid VARCHAR(255) NULL, autenticator VARCHAR(255) NOT NULL, created_at timestamp DEFAULT NULL NULL, device_name VARCHAR(255) NULL, enabled BIT DEFAULT 0 NULL, last_used_at timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, public_key LONGTEXT NOT NULL, sign_count INT NOT NULL, transports_json LONGTEXT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_WEBAUTHN_CREDENTIAL PRIMARY KEY (uid), UNIQUE (autenticator));

-- changeset auto.generated:1825492372-4
ALTER TABLE access_tenant_config ADD webauthn_enabled BIT DEFAULT 0 NULL;

-- changeset auto.generated:1825492372-5
ALTER TABLE access_tenant_config ADD webauthn_rp_id VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-6
ALTER TABLE access_tenant_config ADD webauthn_rp_name VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-7
CREATE INDEX FL_USER_WEBAUTHN_CREDENTIAL_USER ON access_user_webauthn_credential(user);

-- changeset auto.generated:1825492372-8
CREATE UNIQUE INDEX UK_USER_WEBAUTHN_CREDENTIAL_USER_NAME ON access_user_webauthn_credential(user, name);

-- changeset auto.generated:1825492372-9
CREATE INDEX idx_challenge_expires ON _oauth_webauthn_challenge(expires_at);

-- changeset auto.generated:1825492372-10
CREATE INDEX idx_magic_link_expires ON _oauth_magic_link(expires_at);

-- changeset auto.generated:1825492372-11
ALTER TABLE access_user_webauthn_credential ADD CONSTRAINT FK_ACCESS_USER_WEBAUTHN_CREDENTIAL_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

