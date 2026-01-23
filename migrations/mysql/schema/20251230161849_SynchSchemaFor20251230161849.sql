-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE _audit_action (id CHAR(36) NOT NULL, occurred_at datetime NOT NULL, actor_id VARCHAR(255) NOT NULL, actor_type VARCHAR(50) NOT NULL, actor_ip VARCHAR(45) NULL, tenant_id VARCHAR(255) NULL, session_id VARCHAR(255) NULL, client_id VARCHAR(255) NULL, user_agent TEXT NULL, request_method VARCHAR(10) NOT NULL, request_path TEXT NOT NULL, action_type VARCHAR(255) NOT NULL, metadata LONGTEXT NULL, CONSTRAINT PK__AUDIT_ACTION PRIMARY KEY (id));

-- changeset auto.generated:1825492372-2
CREATE TABLE _audit_change (id CHAR(36) NOT NULL, action_id CHAR(36) NOT NULL, target_type VARCHAR(100) NOT NULL, target_id VARCHAR(255) NOT NULL, change_type VARCHAR(20) DEFAULT 'update' NOT NULL, change_order SMALLINT DEFAULT 0 NOT NULL, payload LONGTEXT NOT NULL, metadata LONGTEXT NULL, CONSTRAINT PK__AUDIT_CHANGE PRIMARY KEY (id));

-- changeset auto.generated:1825492372-3
CREATE TABLE _entity_changelog (entity_type VARCHAR(255) NOT NULL, entity_id VARCHAR(255) NOT NULL, deleted BIT DEFAULT 0 NOT NULL, changed_at timestamp NOT NULL, payload LONGTEXT NULL, CONSTRAINT PK__ENTITY_CHANGELOG PRIMARY KEY (entity_type, entity_id));

-- changeset auto.generated:1825492372-4
CREATE TABLE _entity_changelog_cursor (client_id VARCHAR(255) NOT NULL, entity_type VARCHAR(255) NOT NULL, last_changed_at timestamp NOT NULL, last_entity_id CHAR(36) NOT NULL, CONSTRAINT PK__ENTITY_CHANGELOG_CURSOR PRIMARY KEY (client_id, entity_type));

-- changeset auto.generated:1825492372-5
CREATE TABLE _filestorer (code VARCHAR(250) NOT NULL, temp SMALLINT DEFAULT 0 NOT NULL, name VARCHAR(250) NOT NULL, mime VARCHAR(250) NOT NULL, upload timestamp NOT NULL, bytes LONGBLOB NOT NULL, CONSTRAINT PK__FILESTORER PRIMARY KEY (code));

-- changeset auto.generated:1825492372-6
CREATE TABLE _long_tasks (id VARCHAR(250) NOT NULL, user VARCHAR(250) NOT NULL, expiration timestamp DEFAULT NULL NULL, content TEXT NOT NULL, CONSTRAINT PK__LONG_TASKS PRIMARY KEY (id));

-- changeset auto.generated:1825492372-7
CREATE TABLE _oauth_keys_storer (expiration timestamp NOT NULL, since timestamp NOT NULL, keyid VARCHAR(255) NOT NULL, private TEXT NOT NULL, public TEXT NOT NULL, tenant VARCHAR(100) NOT NULL);

-- changeset auto.generated:1825492372-8
CREATE TABLE _oauth_session (session VARCHAR(255) NOT NULL, expiration timestamp DEFAULT NULL NULL, client_id VARCHAR(250) NOT NULL, issuer VARCHAR(255) NOT NULL, auth_data TEXT NOT NULL, csid TEXT NOT NULL);

-- changeset auto.generated:1825492372-9
CREATE TABLE _oauth_temporal_codes (code VARCHAR(255) NOT NULL, code_data TEXT NOT NULL, expiration timestamp NOT NULL);

-- changeset auto.generated:1825492372-10
CREATE TABLE _oauth_temporal_keys (current VARCHAR(255) NOT NULL, old VARCHAR(255) NOT NULL, expiration timestamp NOT NULL);

-- changeset auto.generated:1825492372-11
CREATE TABLE _output_queue_pending_events (event_id CHAR(36) NOT NULL, status ENUM('pending', 'published', 'failed') DEFAULT 'pending' NOT NULL, next_retry datetime DEFAULT NULL NULL, retries INT UNSIGNED NOT NULL, event_type VARCHAR(120) NOT NULL, schema_version VARCHAR(16) NOT NULL, occurred_at datetime NOT NULL, payload LONGTEXT NOT NULL, diff LONGTEXT NULL, correlation_id CHAR(36) NULL, causation_id CHAR(36) NULL, created_at timestamp NOT NULL, published_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OUTPUT_QUEUE_PENDING_EVENTS PRIMARY KEY (event_id));

-- changeset auto.generated:1825492372-12
CREATE TABLE _prometheus__histograms (name VARCHAR(255) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, bucket VARCHAR(255) NOT NULL, CONSTRAINT PK__PROMETHEUS__HISTOGRAMS PRIMARY KEY (name, labels_hash, bucket));

-- changeset auto.generated:1825492372-13
CREATE TABLE _prometheus__metadata (name VARCHAR(255) NOT NULL, type VARCHAR(9) NOT NULL, metadata TEXT NOT NULL, CONSTRAINT PK__PROMETHEUS__METADATA PRIMARY KEY (name, type));

-- changeset auto.generated:1825492372-14
CREATE TABLE _prometheus__summaries (name VARCHAR(255) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, time INT NOT NULL);

-- changeset auto.generated:1825492372-15
CREATE TABLE _prometheus__values (name VARCHAR(255) NOT NULL, type VARCHAR(9) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, CONSTRAINT PK__PROMETHEUS__VALUES PRIMARY KEY (name, type, labels_hash));

-- changeset auto.generated:1825492372-16
CREATE TABLE _rate_limiter (limiter_key VARCHAR(255) NOT NULL, serialized TEXT NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__RATE_LIMITER PRIMARY KEY (limiter_key));

-- changeset auto.generated:1825492372-17
CREATE TABLE _sync_entities (name VARCHAR(255) NOT NULL, last_start datetime NOT NULL, last_call datetime NOT NULL, cursor_position LONGTEXT NULL);

-- changeset auto.generated:1825492372-18
CREATE TABLE access_api_key_client (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT NOT NULL, `key` VARCHAR(255) NULL, scopes VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_API_KEY_CLIENT PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-19
CREATE TABLE access_relying_party (uid VARCHAR(255) NOT NULL, version INT NOT NULL, api_key VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_RELYING_PARTY PRIMARY KEY (uid), UNIQUE (api_key), UNIQUE (code));

-- changeset auto.generated:1825492372-20
CREATE TABLE access_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, name VARCHAR(255) NOT NULL, relying_party VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-21
CREATE TABLE access_tenant (uid VARCHAR(255) NOT NULL, version INT NOT NULL, domain VARCHAR(255) NOT NULL, enabled BIT NOT NULL, mark_for_delete BIT NOT NULL, mark_for_delete_time timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, root BIT DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_TENANT PRIMARY KEY (uid), UNIQUE (domain), UNIQUE (name));

-- changeset auto.generated:1825492372-22
CREATE TABLE access_tenant_config (uid VARCHAR(255) NOT NULL, version INT NOT NULL, allow_recover_pass BIT DEFAULT 0 NULL, allow_register BIT DEFAULT 0 NULL, disabled_user_email LONGTEXT NULL, enable_register_users BIT DEFAULT 0 NULL, enabled_user_email LONGTEXT NULL, force_mfa BIT NOT NULL, inner_label VARCHAR(255) NULL, recover_pass_email LONGTEXT NULL, registerd_email LONGTEXT NULL, wellcome_email LONGTEXT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_CONFIG PRIMARY KEY (uid), UNIQUE (tenant));

-- changeset auto.generated:1825492372-23
CREATE TABLE access_tenant_login_provider (uid VARCHAR(255) NOT NULL, version INT NOT NULL, certificate LONGTEXT NULL, direct_access BIT DEFAULT 0 NULL, disabled BIT DEFAULT 0 NULL, metadata VARCHAR(255) NULL, name VARCHAR(255) NOT NULL, private_key VARCHAR(255) NULL, public_key VARCHAR(255) NULL, source VARCHAR(255) NOT NULL, users_enabled_by_default BIT NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_LOGIN_PROVIDER PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-24
CREATE TABLE access_tenant_terms_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, activation_date timestamp DEFAULT NULL NULL, attached VARCHAR(255) NULL, enabled BIT NOT NULL, text LONGTEXT NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_TERMS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-25
CREATE TABLE access_trusted_client (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT NOT NULL, public_allow BIT NOT NULL, secret_oauth VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-26
CREATE TABLE access_trusted_client_allowed_redirect (uid VARCHAR(255) NOT NULL, version INT NOT NULL, url VARCHAR(255) NOT NULL, client VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT_ALLOWED_REDIRECT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-27
CREATE TABLE access_user (uid VARCHAR(255) NOT NULL, version INT NOT NULL, approve VARCHAR(255) NULL, blocked_until timestamp DEFAULT NULL NULL, email VARCHAR(255) NULL, enabled BIT DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, provider VARCHAR(255) NULL, second_factor_seed VARCHAR(255) NULL, temporal_password BIT DEFAULT 0 NULL, use_second_factors BIT DEFAULT 0 NULL, wellcome_at timestamp DEFAULT NULL NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-28
CREATE TABLE access_user_accepted_termns_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, accept_date timestamp DEFAULT NULL NULL, conditions VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCEPTED_TERMNS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-29
CREATE TABLE access_user_access_temporal_code (uid VARCHAR(255) NOT NULL, version INT NOT NULL, failed_login_attempts INT DEFAULT NULL NULL, recovery_code VARCHAR(255) NULL, recovery_code_expiration timestamp DEFAULT NULL NULL, register_code VARCHAR(255) NULL, register_code_expiration timestamp DEFAULT NULL NULL, register_code_url VARCHAR(255) NULL, temp_second_factor_seed VARCHAR(255) NULL, temp_second_factor_seed_expiration timestamp DEFAULT NULL NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCESS_TEMPORAL_CODE PRIMARY KEY (uid), UNIQUE (recovery_code), UNIQUE (register_code), UNIQUE (user));

-- changeset auto.generated:1825492372-30
CREATE TABLE access_user_identity (uid VARCHAR(255) NOT NULL, version INT NOT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_IDENTITY PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-31
CREATE TABLE access_user_identity_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `role` VARCHAR(255) NOT NULL, user_identity VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_IDENTITY_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-32
CREATE INDEX FL_ROLE_NAME ON access_role(name);

-- changeset auto.generated:1825492372-33
CREATE INDEX FL_ROLE_RELYING_PARTY ON access_role(relying_party);

-- changeset auto.generated:1825492372-34
CREATE INDEX FL_TENANT_LOGIN_PROVIDER_NAME ON access_tenant_login_provider(name);

-- changeset auto.generated:1825492372-35
CREATE INDEX FL_TENANT_LOGIN_PROVIDER_TENANT ON access_tenant_login_provider(tenant);

-- changeset auto.generated:1825492372-36
CREATE INDEX FL_TENANT_TERMS_OF_USE_TENANT ON access_tenant_terms_of_use(tenant);

-- changeset auto.generated:1825492372-37
CREATE INDEX FL_TRUSTED_CLIENT_ALLOWED_REDIRECT_CLIENT ON access_trusted_client_allowed_redirect(client);

-- changeset auto.generated:1825492372-38
CREATE INDEX FL_USER_ACCEPTED_TERMNS_OF_USE_CONDITIONS ON access_user_accepted_termns_of_use(conditions);

-- changeset auto.generated:1825492372-39
CREATE INDEX FL_USER_ACCEPTED_TERMNS_OF_USE_USERS ON access_user_accepted_termns_of_use(user);

-- changeset auto.generated:1825492372-40
CREATE INDEX FL_USER_IDENTITY_RELYING_PARTYS ON access_user_identity(relying_party);

-- changeset auto.generated:1825492372-41
CREATE INDEX FL_USER_IDENTITY_ROLE_ROLES ON access_user_identity_role(`role`);

-- changeset auto.generated:1825492372-42
CREATE INDEX FL_USER_IDENTITY_ROLE_USER_IDENTITY ON access_user_identity_role(user_identity);

-- changeset auto.generated:1825492372-43
CREATE INDEX FL_USER_IDENTITY_TRUSTED_CLIENTS ON access_user_identity(trusted_client);

-- changeset auto.generated:1825492372-44
CREATE INDEX FL_USER_IDENTITY_USER ON access_user_identity(user);

-- changeset auto.generated:1825492372-45
CREATE INDEX FL_USER_NAME ON access_user(name);

-- changeset auto.generated:1825492372-46
CREATE INDEX FL_USER_TENANT ON access_user(tenant);

-- changeset auto.generated:1825492372-47
CREATE INDEX ST_ROLE_NAME_DESC ON access_role(name DESC);

-- changeset auto.generated:1825492372-48
CREATE INDEX ST_TENANT_LOGIN_PROVIDER_NAME_DESC ON access_tenant_login_provider(name DESC);

-- changeset auto.generated:1825492372-49
CREATE INDEX ST_USER_NAME_DESC ON access_user(name DESC);

-- changeset auto.generated:1825492372-50
CREATE UNIQUE INDEX UK_ROLE_RELYING_PARTY_NAME ON access_role(relying_party, name);

-- changeset auto.generated:1825492372-51
CREATE UNIQUE INDEX UK_TENANT_LOGIN_PROVIDER_TENANT_NAME ON access_tenant_login_provider(tenant, name);

-- changeset auto.generated:1825492372-52
CREATE UNIQUE INDEX UK_USER_ACCEPTED_TERMNS_OF_USE_USER_CONDITIONS ON access_user_accepted_termns_of_use(user, conditions);

-- changeset auto.generated:1825492372-53
CREATE UNIQUE INDEX UK_USER_IDENTITY_ROLE_ROLE_USER_IDENTITY ON access_user_identity_role(`role`, user_identity);

-- changeset auto.generated:1825492372-54
CREATE UNIQUE INDEX UK_USER_TENANT_NAME ON access_user(tenant, name);

-- changeset auto.generated:1825492372-55
CREATE INDEX fk_audit_change_action ON _audit_change(action_id);

-- changeset auto.generated:1825492372-56
CREATE INDEX idx_audit_action_actor ON _audit_action(actor_id);

-- changeset auto.generated:1825492372-57
CREATE INDEX idx_audit_action_occurred_at ON _audit_action(occurred_at);

-- changeset auto.generated:1825492372-58
CREATE INDEX idx_audit_change_target ON _audit_change(target_type, target_id);

-- changeset auto.generated:1825492372-59
CREATE INDEX idx_entity_changelog_ordering ON _entity_changelog(changed_at, entity_id);

-- changeset auto.generated:1825492372-60
CREATE INDEX idx_entity_changelog_type ON _entity_changelog(entity_type);

-- changeset auto.generated:1825492372-61
CREATE INDEX idx_name ON _prometheus__summaries(name);

-- changeset auto.generated:1825492372-62
CREATE INDEX idx_published_at ON _output_queue_pending_events(published_at);

-- changeset auto.generated:1825492372-63
CREATE INDEX idx_status_next_retry ON _output_queue_pending_events(status, next_retry);

-- changeset auto.generated:1825492372-64
ALTER TABLE access_role ADD CONSTRAINT FK_ACCESS_ROLE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-65
ALTER TABLE access_tenant_config ADD CONSTRAINT FK_ACCESS_TENANT_CONFIG_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-66
ALTER TABLE access_tenant_login_provider ADD CONSTRAINT FK_ACCESS_TENANT_LOGIN_PROVIDER_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-67
ALTER TABLE access_tenant_terms_of_use ADD CONSTRAINT FK_ACCESS_TENANT_TERMS_OF_USE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-68
ALTER TABLE access_trusted_client_allowed_redirect ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_ALLOWED_REDIRECT_CLIENT FOREIGN KEY (client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-69
ALTER TABLE access_user_accepted_termns_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMNS_OF_USE_CONDITIONS FOREIGN KEY (conditions) REFERENCES access_tenant_terms_of_use (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-70
ALTER TABLE access_user_accepted_termns_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMNS_OF_USE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-71
ALTER TABLE access_user_access_temporal_code ADD CONSTRAINT FK_ACCESS_USER_ACCESS_TEMPORAL_CODE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-72
ALTER TABLE access_user_identity ADD CONSTRAINT FK_ACCESS_USER_IDENTITY_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-73
ALTER TABLE access_user_identity_role ADD CONSTRAINT FK_ACCESS_USER_IDENTITY_ROLE_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-74
ALTER TABLE access_user_identity_role ADD CONSTRAINT FK_ACCESS_USER_IDENTITY_ROLE_USER_IDENTITY FOREIGN KEY (user_identity) REFERENCES access_user_identity (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-75
ALTER TABLE access_user_identity ADD CONSTRAINT FK_ACCESS_USER_IDENTITY_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-76
ALTER TABLE access_user_identity ADD CONSTRAINT FK_ACCESS_USER_IDENTITY_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-77
ALTER TABLE access_user ADD CONSTRAINT FK_ACCESS_USER_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-78
ALTER TABLE _audit_change ADD CONSTRAINT fk_audit_change_action FOREIGN KEY (action_id) REFERENCES _audit_action (id) ON UPDATE RESTRICT ON DELETE CASCADE;

