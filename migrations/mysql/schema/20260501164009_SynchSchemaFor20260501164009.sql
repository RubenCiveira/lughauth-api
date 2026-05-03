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
CREATE TABLE _oauth_device_codes (device_code VARCHAR(36) NOT NULL, user_code VARCHAR(16) NOT NULL, tenant VARCHAR(64) NOT NULL, client_id VARCHAR(128) NOT NULL, scope TEXT NOT NULL, audiences LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, auth_data LONGTEXT NULL, requested_at datetime NOT NULL, expires_at datetime NOT NULL, last_poll_at datetime DEFAULT NULL NULL, interval_sec INT NOT NULL, CONSTRAINT PK__OAUTH_DEVICE_CODES PRIMARY KEY (device_code));

-- changeset auto.generated:1825492372-8
CREATE TABLE _oauth_keys_storer (expiration timestamp NOT NULL, since timestamp NOT NULL, keyid VARCHAR(255) NOT NULL, private TEXT NOT NULL, public TEXT NOT NULL, tenant VARCHAR(100) NOT NULL);

-- changeset auto.generated:1825492372-9
CREATE TABLE _oauth_magic_link (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_uid VARCHAR(36) NOT NULL, client_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, redirect_uri VARCHAR(500) NOT NULL, scope VARCHAR(500) DEFAULT 'openid email' NOT NULL, state VARCHAR(500) NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, used_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_MAGIC_LINK PRIMARY KEY (uid), UNIQUE (token_hash));

-- changeset auto.generated:1825492372-10
CREATE TABLE _oauth_par_request (request_uri VARCHAR(100) NOT NULL, tenant_id VARCHAR(36) NOT NULL, client_id VARCHAR(36) NOT NULL, params_json TEXT NOT NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, used_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_PAR_REQUEST PRIMARY KEY (request_uri));

-- changeset auto.generated:1825492372-11
CREATE TABLE _oauth_revoked_jti (jti VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, token_type ENUM('access', 'refresh') DEFAULT 'access' NOT NULL, revoked_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_REVOKED_JTI PRIMARY KEY (jti));

-- changeset auto.generated:1825492372-12
CREATE TABLE _oauth_session (session VARCHAR(255) NOT NULL, expiration timestamp DEFAULT NULL NULL, client_id VARCHAR(250) NOT NULL, issuer VARCHAR(255) NOT NULL, auth_data TEXT NOT NULL, csid TEXT NOT NULL, jti VARCHAR(36) NULL, refresh_jti VARCHAR(36) NULL, revoked_at timestamp DEFAULT NULL NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(250) NULL, last_used_at datetime DEFAULT NULL NULL, client_name VARCHAR(200) NULL);

-- changeset auto.generated:1825492372-13
CREATE TABLE _oauth_temporal_codes (code VARCHAR(255) NOT NULL, code_data TEXT NOT NULL, expiration timestamp NOT NULL, code_challenge VARCHAR(128) NULL, code_challenge_method VARCHAR(10) NULL);

-- changeset auto.generated:1825492372-14
CREATE TABLE _oauth_temporal_keys (current VARCHAR(255) NOT NULL, old VARCHAR(255) NOT NULL, expiration timestamp NOT NULL);

-- changeset auto.generated:1825492372-15
CREATE TABLE _oauth_webauthn_challenge (challenge_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_uid VARCHAR(36) NULL, challenge VARCHAR(100) NOT NULL, type ENUM('register', 'authenticate') NOT NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_WEBAUTHN_CHALLENGE PRIMARY KEY (challenge_id));

-- changeset auto.generated:1825492372-16
CREATE TABLE _output_queue_pending_events (event_id CHAR(36) NOT NULL, status ENUM('pending', 'published', 'failed') DEFAULT 'pending' NOT NULL, next_retry datetime DEFAULT NULL NULL, retries INT UNSIGNED NOT NULL, event_type VARCHAR(120) NOT NULL, schema_version VARCHAR(16) NOT NULL, occurred_at datetime NOT NULL, payload LONGTEXT NOT NULL, diff LONGTEXT NULL, correlation_id CHAR(36) NULL, causation_id CHAR(36) NULL, created_at timestamp NOT NULL, published_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OUTPUT_QUEUE_PENDING_EVENTS PRIMARY KEY (event_id));

-- changeset auto.generated:1825492372-17
CREATE TABLE _prometheus__histograms (name VARCHAR(255) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, bucket VARCHAR(255) NOT NULL, CONSTRAINT PK__PROMETHEUS__HISTOGRAMS PRIMARY KEY (name, labels_hash, bucket));

-- changeset auto.generated:1825492372-18
CREATE TABLE _prometheus__metadata (name VARCHAR(255) NOT NULL, type VARCHAR(9) NOT NULL, metadata TEXT NOT NULL, CONSTRAINT PK__PROMETHEUS__METADATA PRIMARY KEY (name, type));

-- changeset auto.generated:1825492372-19
CREATE TABLE _prometheus__summaries (name VARCHAR(255) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, time INT NOT NULL);

-- changeset auto.generated:1825492372-20
CREATE TABLE _prometheus__values (name VARCHAR(255) NOT NULL, type VARCHAR(9) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, CONSTRAINT PK__PROMETHEUS__VALUES PRIMARY KEY (name, type, labels_hash));

-- changeset auto.generated:1825492372-21
CREATE TABLE _rate_limiter (limiter_key VARCHAR(255) NOT NULL, serialized TEXT NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__RATE_LIMITER PRIMARY KEY (limiter_key));

-- changeset auto.generated:1825492372-22
CREATE TABLE _sync_entities (name VARCHAR(255) NOT NULL, last_start datetime NOT NULL, last_call datetime NOT NULL, cursor_position LONGTEXT NULL);

-- changeset auto.generated:1825492372-23
CREATE TABLE access_api_key_client (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT NOT NULL, `key` VARCHAR(255) NULL, scopes VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_API_KEY_CLIENT PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-24
CREATE TABLE access_consent_purpose (uid VARCHAR(255) NOT NULL, version INT NOT NULL, activation_date timestamp NOT NULL, `description` LONGTEXT NOT NULL, `key` VARCHAR(255) NOT NULL, required BIT NOT NULL, title VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_CONSENT_PURPOSE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-25
CREATE TABLE access_relying_party (uid VARCHAR(255) NOT NULL, version INT NOT NULL, api_key VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_RELYING_PARTY PRIMARY KEY (uid), UNIQUE (api_key), UNIQUE (code));

-- changeset auto.generated:1825492372-26
CREATE TABLE access_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, name VARCHAR(255) NOT NULL, relying_party VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-27
CREATE TABLE access_tenant (uid VARCHAR(255) NOT NULL, version INT NOT NULL, domain VARCHAR(255) NOT NULL, enabled BIT NOT NULL, mark_for_delete BIT NOT NULL, mark_for_delete_time timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, root BIT DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_TENANT PRIMARY KEY (uid), UNIQUE (domain), UNIQUE (name));

-- changeset auto.generated:1825492372-28
CREATE TABLE access_tenant_config (uid VARCHAR(255) NOT NULL, version INT NOT NULL, allow_recover_pass BIT DEFAULT 0 NULL, allow_register BIT DEFAULT 0 NULL, disabled_user_email LONGTEXT NULL, dynamic_registration_policy VARCHAR(255) NULL, enable_register_users BIT DEFAULT 0 NULL, enabled_user_email LONGTEXT NULL, force_mfa BIT NOT NULL, inner_label VARCHAR(255) NULL, recover_pass_email LONGTEXT NULL, registerd_email LONGTEXT NULL, webauthn_enabled BIT DEFAULT 0 NULL, webauthn_rp_id VARCHAR(255) NULL, webauthn_rp_name VARCHAR(255) NULL, wellcome_email LONGTEXT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_CONFIG PRIMARY KEY (uid), UNIQUE (tenant));

-- changeset auto.generated:1825492372-29
CREATE TABLE access_tenant_login_provider (uid VARCHAR(255) NOT NULL, version INT NOT NULL, certificate LONGTEXT NULL, direct_access BIT DEFAULT 0 NULL, disabled BIT DEFAULT 0 NULL, metadata VARCHAR(255) NULL, name VARCHAR(255) NOT NULL, private_key VARCHAR(255) NULL, public_key VARCHAR(255) NULL, saml_idp_entity_id VARCHAR(255) NULL, saml_idp_idp_cert VARCHAR(255) NULL, saml_idp_metadata_url VARCHAR(255) NULL, saml_idp_sso_url VARCHAR(255) NULL, source VARCHAR(255) NOT NULL, users_enabled_by_default BIT NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_LOGIN_PROVIDER PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-30
CREATE TABLE access_tenant_terms_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, activation_date timestamp DEFAULT NULL NULL, attached VARCHAR(255) NULL, enabled BIT NOT NULL, text LONGTEXT NOT NULL, relying_party VARCHAR(255) NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_TERMS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-31
CREATE TABLE access_trusted_client (uid VARCHAR(255) NOT NULL, version INT NOT NULL, allow_all_scopes BIT DEFAULT 0 NULL, allowed_scopes_m_2m LONGTEXT NULL, back_channel_logout_session_required BIT DEFAULT 0 NULL, back_channel_logout_uri VARCHAR(255) NULL, client_name VARCHAR(255) NULL, client_uri VARCHAR(255) NULL, code VARCHAR(255) NOT NULL, dynamically_registered BIT DEFAULT 0 NULL, enabled BIT NOT NULL, front_channel_logout_session_required BIT DEFAULT 0 NULL, front_channel_logout_uri VARCHAR(255) NULL, grant_types_json LONGTEXT NULL, jwks_json LONGTEXT NULL, jwks_uri LONGTEXT NULL, logo_uri VARCHAR(255) NULL, m_2m_token_ttl_seconds INT NOT NULL, policy_uri VARCHAR(255) NULL, public_allow BIT NOT NULL, registered_at timestamp DEFAULT NULL NULL, registration_access VARCHAR(255) NULL, request_object_signing_alg VARCHAR(255) NULL, response_types_json LONGTEXT NULL, secret_oauth VARCHAR(255) NULL, token_endpoint_auth_method VARCHAR(255) NOT NULL, tos_uri VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-32
CREATE TABLE access_trusted_client_allowed_redirect (uid VARCHAR(255) NOT NULL, version INT NOT NULL, url VARCHAR(255) NOT NULL, client VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT_ALLOWED_REDIRECT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-33
CREATE TABLE access_user (uid VARCHAR(255) NOT NULL, version INT NOT NULL, approve VARCHAR(255) NULL, blocked_until timestamp DEFAULT NULL NULL, email VARCHAR(255) NULL, enabled BIT DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, provider VARCHAR(255) NULL, second_factor_seed VARCHAR(255) NULL, temporal_password BIT DEFAULT 0 NULL, use_second_factors BIT DEFAULT 0 NULL, wellcome_at timestamp DEFAULT NULL NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-34
CREATE TABLE access_user_accepted_termns_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, accept_date timestamp DEFAULT NULL NULL, ip_address VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, conditions VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCEPTED_TERMNS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-35
CREATE TABLE access_user_access_temporal_code (uid VARCHAR(255) NOT NULL, version INT NOT NULL, failed_login_attempts INT DEFAULT NULL NULL, recovery_code VARCHAR(255) NULL, recovery_code_expiration timestamp DEFAULT NULL NULL, register_code VARCHAR(255) NULL, register_code_expiration timestamp DEFAULT NULL NULL, register_code_url VARCHAR(255) NULL, temp_second_factor_seed VARCHAR(255) NULL, temp_second_factor_seed_expiration timestamp DEFAULT NULL NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCESS_TEMPORAL_CODE PRIMARY KEY (uid), UNIQUE (recovery_code), UNIQUE (register_code), UNIQUE (user));

-- changeset auto.generated:1825492372-36
CREATE TABLE access_user_consent_purposes (uid VARCHAR(255) NOT NULL, version INT NOT NULL, decision_at timestamp DEFAULT NULL NULL, granted BIT NOT NULL, ip_address VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, consent_purpose VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_CONSENT_PURPOSES PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-37
CREATE TABLE access_user_consented_scopes (uid VARCHAR(255) NOT NULL, version INT NOT NULL, decision_at timestamp DEFAULT NULL NULL, granted BIT DEFAULT 0 NULL, ip_address VARCHAR(255) NULL, scope VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, trusted_client VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_CONSENTED_SCOPES PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-38
CREATE TABLE access_user_group_membership (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `groups` LONGTEXT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_GROUP_MEMBERSHIP PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-39
CREATE TABLE access_user_profile (uid VARCHAR(255) NOT NULL, version INT NOT NULL, address_json LONGTEXT NULL, birthdate VARCHAR(255) NULL, family_name VARCHAR(255) NULL, gender VARCHAR(255) NULL, given_name VARCHAR(255) NULL, locale VARCHAR(255) NULL, middle_name VARCHAR(255) NULL, nickname VARCHAR(255) NULL, phone_number VARCHAR(255) NULL, phone_number_verified BIT DEFAULT 0 NULL, picture_url VARCHAR(255) NULL, preferred_username VARCHAR(255) NULL, updated_at VARCHAR(255) NULL, website_url VARCHAR(255) NULL, zoneinfo VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_PROFILE PRIMARY KEY (uid), UNIQUE (user));

-- changeset auto.generated:1825492372-40
CREATE TABLE access_user_role_assignament (uid VARCHAR(255) NOT NULL, version INT NOT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNAMENT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-41
CREATE TABLE access_user_role_assignament_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `role` VARCHAR(255) NOT NULL, user_role_assignament VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNAMENT_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-42
CREATE TABLE access_user_webauthn_credential (uid VARCHAR(255) NOT NULL, version INT NOT NULL, aaguid VARCHAR(255) NULL, autenticator VARCHAR(255) NOT NULL, created_at timestamp DEFAULT NULL NULL, device_name VARCHAR(255) NULL, enabled BIT DEFAULT 0 NULL, last_used_at timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, public_key LONGTEXT NOT NULL, sign_count INT NOT NULL, transports_json LONGTEXT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_WEBAUTHN_CREDENTIAL PRIMARY KEY (uid), UNIQUE (autenticator));

-- changeset auto.generated:1825492372-43
CREATE TABLE document_snippet (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_SNIPPET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-44
CREATE TABLE document_snippet_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, snippet VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_SNIPPET_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-45
CREATE TABLE document_snippet_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, subject VARCHAR(255) NULL, snippet VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_SNIPPET_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-46
CREATE TABLE document_template (uid VARCHAR(255) NOT NULL, version INT NOT NULL, channel VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, theme VARCHAR(255) NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-47
CREATE TABLE document_template_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, template VARCHAR(255) NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-48
CREATE TABLE document_template_variable (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, value VARCHAR(255) NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_VARIABLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-49
CREATE TABLE document_template_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, subject VARCHAR(255) NULL, template VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-50
CREATE TABLE document_theme (uid VARCHAR(255) NOT NULL, version INT NOT NULL, enabled BIT DEFAULT 0 NULL, is_default BIT DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_THEME PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-51
CREATE TABLE document_theme_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, theme VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-52
CREATE TABLE document_theme_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, channel VARCHAR(255) NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, theme VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-53
CREATE TABLE notification_message (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content LONGTEXT NOT NULL, created_at timestamp NOT NULL, lock_at timestamp DEFAULT NULL NULL, retries INT NOT NULL, send_at timestamp DEFAULT NULL NULL, target VARCHAR(255) NOT NULL, urgent BIT NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_NOTIFICATION_MESSAGE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-54
CREATE TABLE notification_smtp_outbound_config (uid VARCHAR(255) NOT NULL, version INT NOT NULL, host VARCHAR(255) NOT NULL, login VARCHAR(255) NOT NULL, max_retries INT NOT NULL, password VARCHAR(255) NOT NULL, port BIT NOT NULL, rate_limit INT NOT NULL, retry_delay INT NOT NULL, sender_email VARCHAR(255) NOT NULL, sender_name VARCHAR(255) NULL, timeout INT NOT NULL, use_tls BIT NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_NOTIFICATION_SMTP_OUTBOUND_CONFIG PRIMARY KEY (uid), UNIQUE (tenant));

-- changeset auto.generated:1825492372-55
CREATE INDEX FL_CONSENT_PURPOSE_TENANT ON access_consent_purpose(tenant);

-- changeset auto.generated:1825492372-56
CREATE INDEX FL_MESSAGE_TENANT ON notification_message(tenant);

-- changeset auto.generated:1825492372-57
CREATE INDEX FL_ROLE_NAME ON access_role(name);

-- changeset auto.generated:1825492372-58
CREATE INDEX FL_ROLE_RELYING_PARTY ON access_role(relying_party);

-- changeset auto.generated:1825492372-59
CREATE INDEX FL_SNIPPET_ASSET_SNIPPETS ON document_snippet_asset(snippet);

-- changeset auto.generated:1825492372-60
CREATE INDEX FL_SNIPPET_TENANT ON document_snippet(tenant);

-- changeset auto.generated:1825492372-61
CREATE INDEX FL_SNIPPET_VERSION_SNIPPET ON document_snippet_version(snippet);

-- changeset auto.generated:1825492372-62
CREATE INDEX FL_TEMPLATE_ASSET_CODE ON document_template_asset(code);

-- changeset auto.generated:1825492372-63
CREATE INDEX FL_TEMPLATE_ASSET_TEMPLATES ON document_template_asset(template);

-- changeset auto.generated:1825492372-64
CREATE INDEX FL_TEMPLATE_ASSET_TENANT ON document_template_asset(tenant);

-- changeset auto.generated:1825492372-65
CREATE INDEX FL_TEMPLATE_CODE ON document_template(code);

-- changeset auto.generated:1825492372-66
CREATE INDEX FL_TEMPLATE_TENANT ON document_template(tenant);

-- changeset auto.generated:1825492372-67
CREATE INDEX FL_TEMPLATE_VARIABLE_TENANT ON document_template_variable(tenant);

-- changeset auto.generated:1825492372-68
CREATE INDEX FL_TEMPLATE_VERSION_TEMPLATES ON document_template_version(template);

-- changeset auto.generated:1825492372-69
CREATE INDEX FL_TENANT_LOGIN_PROVIDER_NAME ON access_tenant_login_provider(name);

-- changeset auto.generated:1825492372-70
CREATE INDEX FL_TENANT_LOGIN_PROVIDER_TENANT ON access_tenant_login_provider(tenant);

-- changeset auto.generated:1825492372-71
CREATE INDEX FL_TENANT_TERMS_OF_USE_RELYING_PARTY ON access_tenant_terms_of_use(relying_party);

-- changeset auto.generated:1825492372-72
CREATE INDEX FL_TENANT_TERMS_OF_USE_TENANT ON access_tenant_terms_of_use(tenant);

-- changeset auto.generated:1825492372-73
CREATE INDEX FL_THEME_ASSET_CODE ON document_theme_asset(code);

-- changeset auto.generated:1825492372-74
CREATE INDEX FL_THEME_ASSET_THEME ON document_theme_asset(theme);

-- changeset auto.generated:1825492372-75
CREATE INDEX FL_THEME_TENANTS ON document_theme(tenant);

-- changeset auto.generated:1825492372-76
CREATE INDEX FL_THEME_VERSION_THEMES ON document_theme_version(theme);

-- changeset auto.generated:1825492372-77
CREATE INDEX FL_TRUSTED_CLIENT_ALLOWED_REDIRECT_CLIENT ON access_trusted_client_allowed_redirect(client);

-- changeset auto.generated:1825492372-78
CREATE INDEX FL_TRUSTED_CLIENT_WITH_BACK_CHANNEL_URL ON access_trusted_client(back_channel_logout_uri);

-- changeset auto.generated:1825492372-79
CREATE INDEX FL_TRUSTED_CLIENT_WITH_FRONT_CHANNEL_URL ON access_trusted_client(front_channel_logout_uri);

-- changeset auto.generated:1825492372-80
CREATE INDEX FL_USER_ACCEPTED_TERMNS_OF_USE_CONDITIONS ON access_user_accepted_termns_of_use(conditions);

-- changeset auto.generated:1825492372-81
CREATE INDEX FL_USER_ACCEPTED_TERMNS_OF_USE_USERS ON access_user_accepted_termns_of_use(user);

-- changeset auto.generated:1825492372-82
CREATE INDEX FL_USER_CONSENTED_SCOPES_TRUSTED_CLIENTS ON access_user_consented_scopes(trusted_client);

-- changeset auto.generated:1825492372-83
CREATE INDEX FL_USER_CONSENTED_SCOPES_USERS ON access_user_consented_scopes(user);

-- changeset auto.generated:1825492372-84
CREATE INDEX FL_USER_CONSENT_PURPOSES_CONSENT_PURPOSES ON access_user_consent_purposes(consent_purpose);

-- changeset auto.generated:1825492372-85
CREATE INDEX FL_USER_CONSENT_PURPOSES_USERS ON access_user_consent_purposes(user);

-- changeset auto.generated:1825492372-86
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_RELYING_PARTY ON access_user_group_membership(relying_party);

-- changeset auto.generated:1825492372-87
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_TRUSTED_CLIENT ON access_user_group_membership(trusted_client);

-- changeset auto.generated:1825492372-88
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_USERS ON access_user_group_membership(user);

-- changeset auto.generated:1825492372-89
CREATE INDEX FL_USER_NAME ON access_user(name);

-- changeset auto.generated:1825492372-90
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_RELYING_PARTY ON access_user_role_assignament(relying_party);

-- changeset auto.generated:1825492372-91
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_ROLE_ROLE ON access_user_role_assignament_role(`role`);

-- changeset auto.generated:1825492372-92
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_ROLE_USER_ROLE_ASSIGNAMENTS ON access_user_role_assignament_role(user_role_assignament);

-- changeset auto.generated:1825492372-93
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_TRUSTED_CLIENT ON access_user_role_assignament(trusted_client);

-- changeset auto.generated:1825492372-94
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_USERS ON access_user_role_assignament(user);

-- changeset auto.generated:1825492372-95
CREATE INDEX FL_USER_TENANT ON access_user(tenant);

-- changeset auto.generated:1825492372-96
CREATE INDEX FL_USER_WEBAUTHN_CREDENTIAL_USER ON access_user_webauthn_credential(user);

-- changeset auto.generated:1825492372-97
CREATE INDEX ST_ROLE_NAME_DESC ON access_role(name DESC);

-- changeset auto.generated:1825492372-98
CREATE INDEX ST_SNIPPET_ASSET_CODE_ASC ON document_snippet_asset(code);

-- changeset auto.generated:1825492372-99
CREATE INDEX ST_SNIPPET_ASSET_CODE_DESC ON document_snippet_asset(code DESC);

-- changeset auto.generated:1825492372-100
CREATE INDEX ST_SNIPPET_CODE_ASC ON document_snippet(code);

-- changeset auto.generated:1825492372-101
CREATE INDEX ST_SNIPPET_CODE_DESC ON document_snippet(code DESC);

-- changeset auto.generated:1825492372-102
CREATE INDEX ST_TEMPLATE_ASSET_CODE_DESC ON document_template_asset(code DESC);

-- changeset auto.generated:1825492372-103
CREATE INDEX ST_TEMPLATE_CODE_DESC ON document_template(code DESC);

-- changeset auto.generated:1825492372-104
CREATE INDEX ST_TEMPLATE_VARIABLE_CODE_ASC ON document_template_variable(code);

-- changeset auto.generated:1825492372-105
CREATE INDEX ST_TEMPLATE_VARIABLE_CODE_DESC ON document_template_variable(code DESC);

-- changeset auto.generated:1825492372-106
CREATE INDEX ST_TENANT_LOGIN_PROVIDER_NAME_DESC ON access_tenant_login_provider(name DESC);

-- changeset auto.generated:1825492372-107
CREATE INDEX ST_THEME_ASSET_CODE_DESC ON document_theme_asset(code DESC);

-- changeset auto.generated:1825492372-108
CREATE INDEX ST_THEME_NAME_ASC ON document_theme(name);

-- changeset auto.generated:1825492372-109
CREATE INDEX ST_THEME_NAME_DESC ON document_theme(name DESC);

-- changeset auto.generated:1825492372-110
CREATE INDEX ST_USER_NAME_DESC ON access_user(name DESC);

-- changeset auto.generated:1825492372-111
CREATE UNIQUE INDEX UK_CONSENT_PURPOSE_TENANT_TITLE ON access_consent_purpose(tenant, title);

-- changeset auto.generated:1825492372-112
CREATE UNIQUE INDEX UK_ROLE_RELYING_PARTY_NAME ON access_role(relying_party, name);

-- changeset auto.generated:1825492372-113
CREATE UNIQUE INDEX UK_SNIPPET_ASSET_CODE_SNIPPET ON document_snippet_asset(code, snippet);

-- changeset auto.generated:1825492372-114
CREATE UNIQUE INDEX UK_SNIPPET_CODE_TENANT ON document_snippet(code, tenant);

-- changeset auto.generated:1825492372-115
CREATE UNIQUE INDEX UK_TEMPLATE_ASSET_CODE_TEMPLATE_TENANT ON document_template_asset(code, template, tenant);

-- changeset auto.generated:1825492372-116
CREATE UNIQUE INDEX UK_TEMPLATE_CODE_TENANT ON document_template(code, tenant);

-- changeset auto.generated:1825492372-117
CREATE UNIQUE INDEX UK_TEMPLATE_VARIABLE_CODE_TENANT ON document_template_variable(code, tenant);

-- changeset auto.generated:1825492372-118
CREATE UNIQUE INDEX UK_TENANT_LOGIN_PROVIDER_TENANT_NAME ON access_tenant_login_provider(tenant, name);

-- changeset auto.generated:1825492372-119
CREATE UNIQUE INDEX UK_THEME_ASSET_CODE_THEME ON document_theme_asset(code, theme);

-- changeset auto.generated:1825492372-120
CREATE UNIQUE INDEX UK_THEME_NAME_TENANT ON document_theme(name, tenant);

-- changeset auto.generated:1825492372-121
CREATE UNIQUE INDEX UK_USER_ACCEPTED_TERMNS_OF_USE_USER_CONDITIONS ON access_user_accepted_termns_of_use(user, conditions);

-- changeset auto.generated:1825492372-122
CREATE UNIQUE INDEX UK_USER_ROLE_ASSIGNAMENT_ROLE_ROLE_USER_ROLE_ASSIGNAMENT ON access_user_role_assignament_role(`role`, user_role_assignament);

-- changeset auto.generated:1825492372-123
CREATE UNIQUE INDEX UK_USER_TENANT_NAME ON access_user(tenant, name);

-- changeset auto.generated:1825492372-124
CREATE UNIQUE INDEX UK_USER_WEBAUTHN_CREDENTIAL_USER_NAME ON access_user_webauthn_credential(user, name);

-- changeset auto.generated:1825492372-125
CREATE INDEX fk_audit_change_action ON _audit_change(action_id);

-- changeset auto.generated:1825492372-126
CREATE INDEX idx_audit_action_actor ON _audit_action(actor_id);

-- changeset auto.generated:1825492372-127
CREATE INDEX idx_audit_action_occurred_at ON _audit_action(occurred_at);

-- changeset auto.generated:1825492372-128
CREATE INDEX idx_audit_change_target ON _audit_change(target_type, target_id);

-- changeset auto.generated:1825492372-129
CREATE INDEX idx_challenge_expires ON _oauth_webauthn_challenge(expires_at);

-- changeset auto.generated:1825492372-130
CREATE INDEX idx_device_client ON _oauth_device_codes(client_id);

-- changeset auto.generated:1825492372-131
CREATE INDEX idx_device_expires ON _oauth_device_codes(expires_at);

-- changeset auto.generated:1825492372-132
CREATE INDEX idx_device_status ON _oauth_device_codes(status);

-- changeset auto.generated:1825492372-133
CREATE INDEX idx_entity_changelog_ordering ON _entity_changelog(changed_at, entity_id);

-- changeset auto.generated:1825492372-134
CREATE INDEX idx_entity_changelog_type ON _entity_changelog(entity_type);

-- changeset auto.generated:1825492372-135
CREATE INDEX idx_magic_link_expires ON _oauth_magic_link(expires_at);

-- changeset auto.generated:1825492372-136
CREATE INDEX idx_name ON _prometheus__summaries(name);

-- changeset auto.generated:1825492372-137
CREATE INDEX idx_par_expires ON _oauth_par_request(expires_at);

-- changeset auto.generated:1825492372-138
CREATE INDEX idx_par_tenant ON _oauth_par_request(tenant_id);

-- changeset auto.generated:1825492372-139
CREATE INDEX idx_published_at ON _output_queue_pending_events(published_at);

-- changeset auto.generated:1825492372-140
CREATE INDEX idx_revoked_expires ON _oauth_revoked_jti(expires_at);

-- changeset auto.generated:1825492372-141
CREATE INDEX idx_revoked_tenant ON _oauth_revoked_jti(tenant_id);

-- changeset auto.generated:1825492372-142
CREATE INDEX idx_session_jti ON _oauth_session(jti);

-- changeset auto.generated:1825492372-143
CREATE INDEX idx_session_refresh_jti ON _oauth_session(refresh_jti);

-- changeset auto.generated:1825492372-144
CREATE INDEX idx_status_next_retry ON _output_queue_pending_events(status, next_retry);

-- changeset auto.generated:1825492372-145
CREATE UNIQUE INDEX uq_device_user_code ON _oauth_device_codes(tenant, user_code);

-- changeset auto.generated:1825492372-146
ALTER TABLE access_consent_purpose ADD CONSTRAINT FK_ACCESS_CONSENT_PURPOSE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-147
ALTER TABLE access_role ADD CONSTRAINT FK_ACCESS_ROLE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-148
ALTER TABLE access_tenant_config ADD CONSTRAINT FK_ACCESS_TENANT_CONFIG_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-149
ALTER TABLE access_tenant_login_provider ADD CONSTRAINT FK_ACCESS_TENANT_LOGIN_PROVIDER_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-150
ALTER TABLE access_tenant_terms_of_use ADD CONSTRAINT FK_ACCESS_TENANT_TERMS_OF_USE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-151
ALTER TABLE access_tenant_terms_of_use ADD CONSTRAINT FK_ACCESS_TENANT_TERMS_OF_USE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-152
ALTER TABLE access_trusted_client_allowed_redirect ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_ALLOWED_REDIRECT_CLIENT FOREIGN KEY (client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-153
ALTER TABLE access_user_accepted_termns_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMNS_OF_USE_CONDITIONS FOREIGN KEY (conditions) REFERENCES access_tenant_terms_of_use (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-154
ALTER TABLE access_user_accepted_termns_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMNS_OF_USE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-155
ALTER TABLE access_user_access_temporal_code ADD CONSTRAINT FK_ACCESS_USER_ACCESS_TEMPORAL_CODE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-156
ALTER TABLE access_user_consented_scopes ADD CONSTRAINT FK_ACCESS_USER_CONSENTED_SCOPES_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-157
ALTER TABLE access_user_consented_scopes ADD CONSTRAINT FK_ACCESS_USER_CONSENTED_SCOPES_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-158
ALTER TABLE access_user_consent_purposes ADD CONSTRAINT FK_ACCESS_USER_CONSENT_PURPOSES_CONSENT_PURPOSE FOREIGN KEY (consent_purpose) REFERENCES access_consent_purpose (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-159
ALTER TABLE access_user_consent_purposes ADD CONSTRAINT FK_ACCESS_USER_CONSENT_PURPOSES_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-160
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-161
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-162
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-163
ALTER TABLE access_user_profile ADD CONSTRAINT FK_ACCESS_USER_PROFILE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-164
ALTER TABLE access_user_role_assignament ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-165
ALTER TABLE access_user_role_assignament_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_ROLE_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-166
ALTER TABLE access_user_role_assignament_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_ROLE_USER_ROLE_ASSIGNAMENT FOREIGN KEY (user_role_assignament) REFERENCES access_user_role_assignament (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-167
ALTER TABLE access_user_role_assignament ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-168
ALTER TABLE access_user_role_assignament ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-169
ALTER TABLE access_user ADD CONSTRAINT FK_ACCESS_USER_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-170
ALTER TABLE access_user_webauthn_credential ADD CONSTRAINT FK_ACCESS_USER_WEBAUTHN_CREDENTIAL_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-171
ALTER TABLE document_snippet_asset ADD CONSTRAINT FK_DOCUMENT_SNIPPET_ASSET_SNIPPET FOREIGN KEY (snippet) REFERENCES document_snippet (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-172
ALTER TABLE document_snippet ADD CONSTRAINT FK_DOCUMENT_SNIPPET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-173
ALTER TABLE document_snippet_version ADD CONSTRAINT FK_DOCUMENT_SNIPPET_VERSION_SNIPPET FOREIGN KEY (snippet) REFERENCES document_snippet (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-174
ALTER TABLE document_template_asset ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_ASSET_TEMPLATE FOREIGN KEY (template) REFERENCES document_template (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-175
ALTER TABLE document_template_asset ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_ASSET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-176
ALTER TABLE document_template ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-177
ALTER TABLE document_template_variable ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_VARIABLE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-178
ALTER TABLE document_template_version ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_VERSION_TEMPLATE FOREIGN KEY (template) REFERENCES document_template (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-179
ALTER TABLE document_theme_asset ADD CONSTRAINT FK_DOCUMENT_THEME_ASSET_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-180
ALTER TABLE document_theme ADD CONSTRAINT FK_DOCUMENT_THEME_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-181
ALTER TABLE document_theme_version ADD CONSTRAINT FK_DOCUMENT_THEME_VERSION_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-182
ALTER TABLE notification_message ADD CONSTRAINT FK_NOTIFICATION_MESSAGE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-183
ALTER TABLE notification_smtp_outbound_config ADD CONSTRAINT FK_NOTIFICATION_SMTP_OUTBOUND_CONFIG_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-184
ALTER TABLE _audit_change ADD CONSTRAINT fk_audit_change_action FOREIGN KEY (action_id) REFERENCES _audit_action (id) ON UPDATE RESTRICT ON DELETE CASCADE;

