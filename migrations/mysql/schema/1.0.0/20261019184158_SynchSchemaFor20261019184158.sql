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
CREATE TABLE _oauth_audit_log (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, event_type VARCHAR(64) NOT NULL, user_id VARCHAR(36) NULL, client_id VARCHAR(36) NULL, session_id VARCHAR(255) NULL, jti VARCHAR(36) NULL, grant_type VARCHAR(64) NULL, scope TEXT NULL, acr VARCHAR(8) NULL, ip_address VARCHAR(64) NULL, user_agent VARCHAR(512) NULL, failure_reason VARCHAR(64) NULL, payload_json MEDIUMTEXT NULL, created_at datetime NOT NULL, CONSTRAINT PK__OAUTH_AUDIT_LOG PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-8
CREATE TABLE _oauth_delegated_state (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, state_token VARCHAR(128) NOT NULL, provider_id VARCHAR(36) NOT NULL, session_context_json MEDIUMTEXT NOT NULL, created_at datetime NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_DELEGATED_STATE PRIMARY KEY (uid), UNIQUE (state_token));

-- changeset auto.generated:1825492372-9
CREATE TABLE _oauth_device_codes (device_code VARCHAR(36) NOT NULL, user_code VARCHAR(16) NOT NULL, tenant VARCHAR(64) NOT NULL, client_id VARCHAR(128) NOT NULL, scope TEXT NOT NULL, audiences LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, auth_data LONGTEXT NULL, requested_at datetime NOT NULL, expires_at datetime NOT NULL, last_poll_at datetime DEFAULT NULL NULL, interval_sec INT NOT NULL, CONSTRAINT PK__OAUTH_DEVICE_CODES PRIMARY KEY (device_code));

-- changeset auto.generated:1825492372-10
CREATE TABLE _oauth_keys_storer (expiration timestamp NOT NULL, since timestamp NOT NULL, keyid VARCHAR(255) NOT NULL, private TEXT NOT NULL, public TEXT NOT NULL, tenant VARCHAR(100) NOT NULL);

-- changeset auto.generated:1825492372-11
CREATE TABLE _oauth_magic_link (uid VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_uid VARCHAR(36) NOT NULL, client_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, redirect_uri VARCHAR(500) NOT NULL, nonce VARCHAR(500) NULL, scope VARCHAR(500) DEFAULT 'openid email' NOT NULL, state VARCHAR(500) NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, used_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_MAGIC_LINK PRIMARY KEY (uid), UNIQUE (token_hash));

-- changeset auto.generated:1825492372-12
CREATE TABLE _oauth_par_request (request_uri VARCHAR(100) NOT NULL, tenant_id VARCHAR(36) NOT NULL, client_id VARCHAR(36) NOT NULL, params_json TEXT NOT NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, used_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_PAR_REQUEST PRIMARY KEY (request_uri));

-- changeset auto.generated:1825492372-13
CREATE TABLE _oauth_revoked_jti (jti VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, token_type ENUM('access', 'refresh') DEFAULT 'access' NOT NULL, revoked_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_REVOKED_JTI PRIMARY KEY (jti));

-- changeset auto.generated:1825492372-14
CREATE TABLE _oauth_revoked_refresh_family (family_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, revoked_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_REVOKED_REFRESH_FAMILY PRIMARY KEY (family_id));

-- changeset auto.generated:1825492372-15
CREATE TABLE _oauth_session (session VARCHAR(255) NOT NULL, user_uid VARCHAR(36) NOT NULL, expiration timestamp DEFAULT NULL NULL, client_id VARCHAR(250) NOT NULL, issuer VARCHAR(255) NOT NULL, auth_data TEXT NOT NULL, csid TEXT NOT NULL, revoked_at timestamp DEFAULT NULL NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(250) NULL, last_used_at datetime DEFAULT NULL NULL, client_name VARCHAR(200) NULL, auth_time datetime DEFAULT NULL NULL, acr TINYINT DEFAULT 0 NOT NULL, sso_clients_json MEDIUMTEXT NULL, CONSTRAINT PK__OAUTH_SESSION PRIMARY KEY (session));

-- changeset auto.generated:1825492372-16
CREATE TABLE _oauth_session_grant (id VARCHAR(36) NOT NULL, session VARCHAR(255) NOT NULL, client_id VARCHAR(250) NOT NULL, grant_type VARCHAR(50) NOT NULL, scope TEXT NULL, audiences TEXT NOT NULL, auth_data TEXT NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, revoked_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OAUTH_SESSION_GRANT PRIMARY KEY (id));

-- changeset auto.generated:1825492372-17
CREATE TABLE _oauth_session_token (id VARCHAR(36) NOT NULL, session VARCHAR(255) NOT NULL, jti VARCHAR(36) NOT NULL, refresh_jti VARCHAR(36) NOT NULL, issued_at datetime NOT NULL, expires_at datetime NOT NULL, revoked_at datetime DEFAULT NULL NULL, grant_id VARCHAR(36) NULL, client_id VARCHAR(250) NULL, scope TEXT NULL, audiences TEXT NULL, auth_data TEXT NULL, CONSTRAINT PK__OAUTH_SESSION_TOKEN PRIMARY KEY (id), UNIQUE (jti), UNIQUE (refresh_jti));

-- changeset auto.generated:1825492372-18
CREATE TABLE _oauth_temporal_codes (code VARCHAR(255) NOT NULL, code_data TEXT NOT NULL, expiration timestamp NOT NULL, code_challenge VARCHAR(128) NULL, code_challenge_method VARCHAR(10) NULL, UNIQUE (code));

-- changeset auto.generated:1825492372-19
CREATE TABLE _oauth_temporal_keys (current VARCHAR(255) NOT NULL, old VARCHAR(255) NOT NULL, expiration timestamp NOT NULL);

-- changeset auto.generated:1825492372-20
CREATE TABLE _oauth_webauthn_challenge (challenge_id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_uid VARCHAR(36) NULL, challenge VARCHAR(100) NOT NULL, type ENUM('register', 'authenticate') NOT NULL, created_at datetime DEFAULT NOW() NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__OAUTH_WEBAUTHN_CHALLENGE PRIMARY KEY (challenge_id));

-- changeset auto.generated:1825492372-21
CREATE TABLE _output_queue_pending_events (event_id CHAR(36) NOT NULL, status ENUM('pending', 'published', 'failed') DEFAULT 'pending' NOT NULL, next_retry datetime DEFAULT NULL NULL, retries INT UNSIGNED NOT NULL, event_type VARCHAR(120) NOT NULL, schema_version VARCHAR(16) NOT NULL, occurred_at datetime NOT NULL, payload LONGTEXT NOT NULL, diff LONGTEXT NULL, correlation_id CHAR(36) NULL, causation_id CHAR(36) NULL, created_at timestamp NOT NULL, published_at datetime DEFAULT NULL NULL, CONSTRAINT PK__OUTPUT_QUEUE_PENDING_EVENTS PRIMARY KEY (event_id));

-- changeset auto.generated:1825492372-22
CREATE TABLE _prometheus__histograms (name VARCHAR(255) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, bucket VARCHAR(255) NOT NULL, CONSTRAINT PK__PROMETHEUS__HISTOGRAMS PRIMARY KEY (name, labels_hash, bucket));

-- changeset auto.generated:1825492372-23
CREATE TABLE _prometheus__metadata (name VARCHAR(255) NOT NULL, type VARCHAR(9) NOT NULL, metadata TEXT NOT NULL, CONSTRAINT PK__PROMETHEUS__METADATA PRIMARY KEY (name, type));

-- changeset auto.generated:1825492372-24
CREATE TABLE _prometheus__summaries (name VARCHAR(255) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, time INT NOT NULL);

-- changeset auto.generated:1825492372-25
CREATE TABLE _prometheus__values (name VARCHAR(255) NOT NULL, type VARCHAR(9) NOT NULL, labels_hash VARCHAR(64) NOT NULL, labels TEXT NOT NULL, value DOUBLE DEFAULT 0 NULL, CONSTRAINT PK__PROMETHEUS__VALUES PRIMARY KEY (name, type, labels_hash));

-- changeset auto.generated:1825492372-26
CREATE TABLE _rate_limiter (limiter_key VARCHAR(255) NOT NULL, serialized TEXT NOT NULL, expires_at datetime NOT NULL, CONSTRAINT PK__RATE_LIMITER PRIMARY KEY (limiter_key));

-- changeset auto.generated:1825492372-27
CREATE TABLE _sync_entities (name VARCHAR(255) NOT NULL, last_start datetime NOT NULL, last_call datetime NOT NULL, cursor_position LONGTEXT NULL);

-- changeset auto.generated:1825492372-28
CREATE TABLE access_api_key_client (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT NOT NULL, `key` VARCHAR(255) NULL, scopes VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_API_KEY_CLIENT PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-29
CREATE TABLE access_consent_purpose (uid VARCHAR(255) NOT NULL, version INT NOT NULL, activation_date timestamp NOT NULL, `description` LONGTEXT NOT NULL, `key` VARCHAR(255) NOT NULL, required BIT NOT NULL, title VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_CONSENT_PURPOSE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-30
CREATE TABLE access_dcr_initial_access_token (uid VARCHAR(255) NOT NULL, version INT NOT NULL, allowed_grant_types LONGTEXT NULL, `description` VARCHAR(255) NULL, expires_at timestamp NOT NULL, max_uses INT NOT NULL, revoked_at timestamp DEFAULT NULL NULL, token_hash VARCHAR(255) NOT NULL, uses INT NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_DCR_INITIAL_ACCESS_TOKEN PRIMARY KEY (uid), UNIQUE (token_hash));

-- changeset auto.generated:1825492372-31
CREATE TABLE access_relying_party (uid VARCHAR(255) NOT NULL, version INT NOT NULL, api_key VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_RELYING_PARTY PRIMARY KEY (uid), UNIQUE (api_key), UNIQUE (code));

-- changeset auto.generated:1825492372-32
CREATE TABLE access_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, name VARCHAR(255) NOT NULL, relying_party VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-33
CREATE TABLE access_tenant (uid VARCHAR(255) NOT NULL, version INT NOT NULL, domain VARCHAR(255) NOT NULL, enabled BIT NOT NULL, mark_for_delete BIT NOT NULL, mark_for_delete_time timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, root BIT DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_TENANT PRIMARY KEY (uid), UNIQUE (domain), UNIQUE (name));

-- changeset auto.generated:1825492372-34
CREATE TABLE access_tenant_config (uid VARCHAR(255) NOT NULL, version INT NOT NULL, acr_values_supported LONGTEXT NULL, allow_password_grant BIT NOT NULL, allow_recover_pass BIT DEFAULT 0 NULL, allow_register BIT DEFAULT 0 NULL, dcr_allowed_auth_methods LONGTEXT NULL, dcr_allowed_grant_types LONGTEXT NULL, disabled_user_email LONGTEXT NULL, dynamic_registration_policy VARCHAR(255) NULL, enable_register_users BIT DEFAULT 0 NULL, enabled_user_email LONGTEXT NULL, force_mfa BIT NOT NULL, inner_label VARCHAR(255) NULL, invitation_expiry_days INT NOT NULL, magic_link_enabled BIT DEFAULT 0 NULL, magic_link_expiry_minutes INT NOT NULL, recover_pass_email LONGTEXT NULL, refresh_token_max_lifetime_seconds INT NOT NULL, refresh_token_ttl_seconds INT NOT NULL, registered_email LONGTEXT NULL, require_email_verification BIT NOT NULL, require_pushed_authorization_requests BIT NOT NULL, session_sso_ttl_seconds INT NOT NULL, webauthn_enabled BIT DEFAULT 0 NULL, webauthn_rp_id VARCHAR(255) NULL, webauthn_rp_name VARCHAR(255) NULL, welcome_email LONGTEXT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_CONFIG PRIMARY KEY (uid), UNIQUE (tenant));

-- changeset auto.generated:1825492372-35
CREATE TABLE access_tenant_login_provider (uid VARCHAR(255) NOT NULL, version INT NOT NULL, account_link_policy VARCHAR(255) NOT NULL, certificate LONGTEXT NULL, direct_access BIT DEFAULT 0 NULL, disabled BIT DEFAULT 0 NULL, metadata VARCHAR(255) NULL, name VARCHAR(255) NOT NULL, oidc_claim_mapping LONGTEXT NULL, oidc_discovery_url VARCHAR(255) NULL, oidc_scopes VARCHAR(255) NULL, private_key VARCHAR(255) NULL, public_key VARCHAR(255) NULL, saml_idp_cert VARCHAR(255) NULL, saml_idp_entity_id VARCHAR(255) NULL, saml_idp_metadata_url VARCHAR(255) NULL, saml_idp_sso_url VARCHAR(255) NULL, source VARCHAR(255) NOT NULL, users_enabled_by_default BIT NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_LOGIN_PROVIDER PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-36
CREATE TABLE access_tenant_terms_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, activation_date timestamp DEFAULT NULL NULL, attached VARCHAR(255) NULL, enabled BIT NOT NULL, text LONGTEXT NOT NULL, relying_party VARCHAR(255) NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_TERMS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-37
CREATE TABLE access_trusted_client (uid VARCHAR(255) NOT NULL, version INT NOT NULL, allow_all_scopes BIT DEFAULT 0 NULL, allowed_scopes_m_2m LONGTEXT NULL, authorization_details_types LONGTEXT NULL, authorization_signed_response_alg VARCHAR(255) NULL, back_channel_logout_session_required BIT DEFAULT 0 NULL, back_channel_logout_uri VARCHAR(255) NULL, backchannel_client_notification_endpoint VARCHAR(255) NULL, backchannel_token_delivery_mode VARCHAR(255) NULL, client_name VARCHAR(255) NULL, client_uri VARCHAR(255) NULL, code VARCHAR(255) NOT NULL, default_acr_values VARCHAR(255) NULL, default_max_age INT DEFAULT NULL NULL, dpop_bound_access_tokens BIT NOT NULL, dynamically_registered BIT DEFAULT 0 NULL, enabled BIT NOT NULL, front_channel_logout_session_required BIT DEFAULT 0 NULL, front_channel_logout_uri VARCHAR(255) NULL, grant_types_json LONGTEXT NULL, id_token_encrypted_response_alg VARCHAR(255) NULL, id_token_encrypted_response_enc VARCHAR(255) NULL, id_token_signed_response_alg VARCHAR(255) NULL, is_resource_server BIT NOT NULL, jwks_json LONGTEXT NULL, jwks_uri LONGTEXT NULL, logo_uri VARCHAR(255) NULL, m_2m_token_ttl_seconds INT NOT NULL, policy_uri VARCHAR(255) NULL, public_allow BIT NOT NULL, registered_at timestamp DEFAULT NULL NULL, registration_access VARCHAR(255) NULL, request_object_signing_alg VARCHAR(255) NULL, require_pkce BIT NOT NULL, require_pushed_authorization_requests BIT NOT NULL, response_types_json LONGTEXT NULL, secret_oauth VARCHAR(255) NULL, sector_identifier_uri VARCHAR(255) NULL, subject_type VARCHAR(255) NOT NULL, tls_client_auth_subject_dn VARCHAR(255) NULL, tls_client_certificate_bound_access_tokens BIT NOT NULL, token_endpoint_auth_method VARCHAR(255) NOT NULL, token_endpoint_auth_signing_alg VARCHAR(255) NULL, tos_uri VARCHAR(255) NULL, userinfo_encrypted_response_alg VARCHAR(255) NULL, userinfo_encrypted_response_enc VARCHAR(255) NULL, userinfo_signed_response_alg VARCHAR(255) NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-38
CREATE TABLE access_trusted_client_allowed_audience (uid VARCHAR(255) NOT NULL, version INT NOT NULL, audience VARCHAR(255) NOT NULL, client VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT_ALLOWED_AUDIENCE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-39
CREATE TABLE access_trusted_client_allowed_redirect (uid VARCHAR(255) NOT NULL, version INT NOT NULL, url VARCHAR(255) NOT NULL, client VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TRUSTED_CLIENT_ALLOWED_REDIRECT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-40
CREATE TABLE access_user (uid VARCHAR(255) NOT NULL, version INT NOT NULL, approve VARCHAR(255) NULL, blocked_until timestamp DEFAULT NULL NULL, email VARCHAR(255) NULL, email_verified BIT DEFAULT 0 NULL, enabled BIT DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, provider VARCHAR(255) NULL, second_factor_seed VARCHAR(255) NULL, temporal_password BIT DEFAULT 0 NULL, use_second_factors BIT DEFAULT 0 NULL, welcome_at timestamp DEFAULT NULL NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-41
CREATE TABLE access_user_accepted_terms_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, accept_date timestamp DEFAULT NULL NULL, ip_address VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, conditions VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCEPTED_TERMS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-42
CREATE TABLE access_user_access_temporal_code (uid VARCHAR(255) NOT NULL, version INT NOT NULL, failed_login_attempts INT DEFAULT NULL NULL, recovery_code VARCHAR(255) NULL, recovery_code_expiration timestamp DEFAULT NULL NULL, register_code VARCHAR(255) NULL, register_code_expiration timestamp DEFAULT NULL NULL, register_code_url LONGTEXT NULL, temp_second_factor_seed VARCHAR(255) NULL, temp_second_factor_seed_expiration timestamp DEFAULT NULL NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCESS_TEMPORAL_CODE PRIMARY KEY (uid), UNIQUE (recovery_code), UNIQUE (register_code), UNIQUE (user));

-- changeset auto.generated:1825492372-43
CREATE TABLE access_user_consent_purposes (uid VARCHAR(255) NOT NULL, version INT NOT NULL, decision_at timestamp DEFAULT NULL NULL, granted BIT NOT NULL, ip_address VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, consent_purpose VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_CONSENT_PURPOSES PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-44
CREATE TABLE access_user_consented_scopes (uid VARCHAR(255) NOT NULL, version INT NOT NULL, decision_at timestamp DEFAULT NULL NULL, granted BIT DEFAULT 0 NULL, ip_address VARCHAR(255) NULL, scope VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, trusted_client VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_CONSENTED_SCOPES PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-45
CREATE TABLE access_user_group_membership (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `groups` LONGTEXT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_GROUP_MEMBERSHIP PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-46
CREATE TABLE access_user_invitation (uid VARCHAR(255) NOT NULL, version INT NOT NULL, accepted_at timestamp DEFAULT NULL NULL, created_at timestamp NOT NULL, email VARCHAR(255) NOT NULL, expired_at timestamp NOT NULL, metadata_json LONGTEXT NULL, roles VARCHAR(255) NULL, status VARCHAR(255) NULL, token_hash VARCHAR(255) NOT NULL, accepted_by VARCHAR(255) NULL, invited_by VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_INVITATION PRIMARY KEY (uid), UNIQUE (token_hash));

-- changeset auto.generated:1825492372-47
CREATE TABLE access_user_mfa_recovery_code (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code_hash VARCHAR(255) NOT NULL, created_at timestamp NOT NULL, used_at timestamp DEFAULT NULL NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_MFA_RECOVERY_CODE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-48
CREATE TABLE access_user_personal_api_key (uid VARCHAR(255) NOT NULL, version INT NOT NULL, created_at timestamp NOT NULL, enabled BIT NOT NULL, expires_at timestamp DEFAULT NULL NULL, key_hash VARCHAR(255) NOT NULL, last_used_at timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, scopes VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_PERSONAL_API_KEY PRIMARY KEY (uid), UNIQUE (key_hash));

-- changeset auto.generated:1825492372-49
CREATE TABLE access_user_profile (uid VARCHAR(255) NOT NULL, version INT NOT NULL, address_json LONGTEXT NULL, birthdate VARCHAR(255) NULL, family_name VARCHAR(255) NULL, gender VARCHAR(255) NULL, given_name VARCHAR(255) NULL, locale VARCHAR(255) NULL, middle_name VARCHAR(255) NULL, nickname VARCHAR(255) NULL, phone_number VARCHAR(255) NULL, phone_number_verified BIT DEFAULT 0 NULL, picture_url VARCHAR(255) NULL, preferred_username VARCHAR(255) NULL, updated_at VARCHAR(255) NULL, website_url VARCHAR(255) NULL, zoneinfo VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_PROFILE PRIMARY KEY (uid), UNIQUE (user));

-- changeset auto.generated:1825492372-50
CREATE TABLE access_user_role_assignment (uid VARCHAR(255) NOT NULL, version INT NOT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNMENT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-51
CREATE TABLE access_user_role_assignment_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `role` VARCHAR(255) NOT NULL, user_role_assignment VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNMENT_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-52
CREATE TABLE access_user_webauthn_credential (uid VARCHAR(255) NOT NULL, version INT NOT NULL, aaguid VARCHAR(255) NULL, authenticator VARCHAR(255) NOT NULL, created_at timestamp DEFAULT NULL NULL, device_name VARCHAR(255) NULL, enabled BIT DEFAULT 0 NULL, last_used_at timestamp DEFAULT NULL NULL, name VARCHAR(255) NOT NULL, public_key LONGTEXT NOT NULL, sign_count INT NOT NULL, transports_json LONGTEXT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_WEBAUTHN_CREDENTIAL PRIMARY KEY (uid), UNIQUE (authenticator));

-- changeset auto.generated:1825492372-53
CREATE TABLE document_snippet (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_SNIPPET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-54
CREATE TABLE document_snippet_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, snippet VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_SNIPPET_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-55
CREATE TABLE document_snippet_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, subject VARCHAR(255) NULL, snippet VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_SNIPPET_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-56
CREATE TABLE document_template (uid VARCHAR(255) NOT NULL, version INT NOT NULL, channel VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, theme VARCHAR(255) NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-57
CREATE TABLE document_template_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, template VARCHAR(255) NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-58
CREATE TABLE document_template_variable (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, value VARCHAR(255) NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_VARIABLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-59
CREATE TABLE document_template_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, subject VARCHAR(255) NULL, template VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-60
CREATE TABLE document_theme (uid VARCHAR(255) NOT NULL, version INT NOT NULL, enabled BIT DEFAULT 0 NULL, is_default BIT DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_THEME PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-61
CREATE TABLE document_theme_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, theme VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-62
CREATE TABLE document_theme_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, channel VARCHAR(255) NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, theme VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-63
CREATE TABLE notification_message (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content LONGTEXT NOT NULL, created_at timestamp NOT NULL, lock_at timestamp DEFAULT NULL NULL, retries INT NOT NULL, send_at timestamp DEFAULT NULL NULL, target VARCHAR(255) NOT NULL, urgent BIT NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_NOTIFICATION_MESSAGE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-64
CREATE TABLE notification_smtp_outbound_config (uid VARCHAR(255) NOT NULL, version INT NOT NULL, host VARCHAR(255) NOT NULL, login VARCHAR(255) NOT NULL, max_retries INT NOT NULL, password VARCHAR(255) NOT NULL, port INT NOT NULL, rate_limit INT NOT NULL, retry_delay INT NOT NULL, sender_email VARCHAR(255) NOT NULL, sender_name VARCHAR(255) NULL, timeout INT NOT NULL, use_tls BIT NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_NOTIFICATION_SMTP_OUTBOUND_CONFIG PRIMARY KEY (uid), UNIQUE (tenant));

-- changeset auto.generated:1825492372-65
CREATE INDEX FK__OAUTH_SESSION_GRANT_SESSION ON _oauth_session_grant(session);

-- changeset auto.generated:1825492372-66
CREATE INDEX FK__OAUTH_SESSION_TOKEN_GRANT ON _oauth_session_token(grant_id);

-- changeset auto.generated:1825492372-67
CREATE INDEX FL_CONSENT_PURPOSE_TENANT ON access_consent_purpose(tenant);

-- changeset auto.generated:1825492372-68
CREATE INDEX FL_DCR_INITIAL_ACCESS_TOKEN_DESCRIPTION ON access_dcr_initial_access_token(`description`);

-- changeset auto.generated:1825492372-69
CREATE INDEX FL_DCR_INITIAL_ACCESS_TOKEN_TENANT ON access_dcr_initial_access_token(tenant);

-- changeset auto.generated:1825492372-70
CREATE INDEX FL_MESSAGE_TENANT ON notification_message(tenant);

-- changeset auto.generated:1825492372-71
CREATE INDEX FL_ROLE_NAME ON access_role(name);

-- changeset auto.generated:1825492372-72
CREATE INDEX FL_ROLE_RELYING_PARTY ON access_role(relying_party);

-- changeset auto.generated:1825492372-73
CREATE INDEX FL_SNIPPET_ASSET_SNIPPETS ON document_snippet_asset(snippet);

-- changeset auto.generated:1825492372-74
CREATE INDEX FL_SNIPPET_TENANT ON document_snippet(tenant);

-- changeset auto.generated:1825492372-75
CREATE INDEX FL_SNIPPET_VERSION_SNIPPET ON document_snippet_version(snippet);

-- changeset auto.generated:1825492372-76
CREATE INDEX FL_TEMPLATE_ASSET_CODE ON document_template_asset(code);

-- changeset auto.generated:1825492372-77
CREATE INDEX FL_TEMPLATE_ASSET_TEMPLATES ON document_template_asset(template);

-- changeset auto.generated:1825492372-78
CREATE INDEX FL_TEMPLATE_ASSET_TENANT ON document_template_asset(tenant);

-- changeset auto.generated:1825492372-79
CREATE INDEX FL_TEMPLATE_CODE ON document_template(code);

-- changeset auto.generated:1825492372-80
CREATE INDEX FL_TEMPLATE_TENANT ON document_template(tenant);

-- changeset auto.generated:1825492372-81
CREATE INDEX FL_TEMPLATE_VARIABLE_TENANT ON document_template_variable(tenant);

-- changeset auto.generated:1825492372-82
CREATE INDEX FL_TEMPLATE_VERSION_TEMPLATES ON document_template_version(template);

-- changeset auto.generated:1825492372-83
CREATE INDEX FL_TENANT_LOGIN_PROVIDER_NAME ON access_tenant_login_provider(name);

-- changeset auto.generated:1825492372-84
CREATE INDEX FL_TENANT_LOGIN_PROVIDER_TENANT ON access_tenant_login_provider(tenant);

-- changeset auto.generated:1825492372-85
CREATE INDEX FL_TENANT_TERMS_OF_USE_RELYING_PARTY ON access_tenant_terms_of_use(relying_party);

-- changeset auto.generated:1825492372-86
CREATE INDEX FL_TENANT_TERMS_OF_USE_TENANT ON access_tenant_terms_of_use(tenant);

-- changeset auto.generated:1825492372-87
CREATE INDEX FL_THEME_ASSET_CODE ON document_theme_asset(code);

-- changeset auto.generated:1825492372-88
CREATE INDEX FL_THEME_ASSET_THEME ON document_theme_asset(theme);

-- changeset auto.generated:1825492372-89
CREATE INDEX FL_THEME_GLOBAL ON document_theme(tenant);

-- changeset auto.generated:1825492372-90
CREATE INDEX FL_THEME_VERSION_THEMES ON document_theme_version(theme);

-- changeset auto.generated:1825492372-91
CREATE INDEX FL_TRUSTED_CLIENT_ALLOWED_AUDIENCE_CLIENTS ON access_trusted_client_allowed_audience(client);

-- changeset auto.generated:1825492372-92
CREATE INDEX FL_TRUSTED_CLIENT_ALLOWED_REDIRECT_CLIENT ON access_trusted_client_allowed_redirect(client);

-- changeset auto.generated:1825492372-93
CREATE INDEX FL_TRUSTED_CLIENT_CODE ON access_trusted_client(code);

-- changeset auto.generated:1825492372-94
CREATE INDEX FL_TRUSTED_CLIENT_TENANT ON access_trusted_client(tenant);

-- changeset auto.generated:1825492372-95
CREATE INDEX FL_TRUSTED_CLIENT_WITH_BACK_CHANNEL_URL ON access_trusted_client(back_channel_logout_uri);

-- changeset auto.generated:1825492372-96
CREATE INDEX FL_TRUSTED_CLIENT_WITH_FRONT_CHANNEL_URL ON access_trusted_client(front_channel_logout_uri);

-- changeset auto.generated:1825492372-97
CREATE INDEX FL_USER_ACCEPTED_TERMS_OF_USE_CONDITIONS ON access_user_accepted_terms_of_use(conditions);

-- changeset auto.generated:1825492372-98
CREATE INDEX FL_USER_ACCEPTED_TERMS_OF_USE_USERS ON access_user_accepted_terms_of_use(user);

-- changeset auto.generated:1825492372-99
CREATE INDEX FL_USER_CONSENTED_SCOPES_TRUSTED_CLIENTS ON access_user_consented_scopes(trusted_client);

-- changeset auto.generated:1825492372-100
CREATE INDEX FL_USER_CONSENTED_SCOPES_USERS ON access_user_consented_scopes(user);

-- changeset auto.generated:1825492372-101
CREATE INDEX FL_USER_CONSENT_PURPOSES_CONSENT_PURPOSES ON access_user_consent_purposes(consent_purpose);

-- changeset auto.generated:1825492372-102
CREATE INDEX FL_USER_CONSENT_PURPOSES_USERS ON access_user_consent_purposes(user);

-- changeset auto.generated:1825492372-103
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_RELYING_PARTY ON access_user_group_membership(relying_party);

-- changeset auto.generated:1825492372-104
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_TRUSTED_CLIENT ON access_user_group_membership(trusted_client);

-- changeset auto.generated:1825492372-105
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_USERS ON access_user_group_membership(user);

-- changeset auto.generated:1825492372-106
CREATE INDEX FL_USER_INVITATION_ACCEPTED_BY ON access_user_invitation(accepted_by);

-- changeset auto.generated:1825492372-107
CREATE INDEX FL_USER_INVITATION_EMAIL ON access_user_invitation(email);

-- changeset auto.generated:1825492372-108
CREATE INDEX FL_USER_INVITATION_INVITED_BIES ON access_user_invitation(invited_by);

-- changeset auto.generated:1825492372-109
CREATE INDEX FL_USER_INVITATION_STATUS ON access_user_invitation(status);

-- changeset auto.generated:1825492372-110
CREATE INDEX FL_USER_INVITATION_TENANT ON access_user_invitation(tenant);

-- changeset auto.generated:1825492372-111
CREATE INDEX FL_USER_MFA_RECOVERY_CODE_USER ON access_user_mfa_recovery_code(user);

-- changeset auto.generated:1825492372-112
CREATE INDEX FL_USER_NAME ON access_user(name);

-- changeset auto.generated:1825492372-113
CREATE INDEX FL_USER_PERSONAL_API_KEY_USER ON access_user_personal_api_key(user);

-- changeset auto.generated:1825492372-114
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_RELYING_PARTY ON access_user_role_assignment(relying_party);

-- changeset auto.generated:1825492372-115
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_ROLE_ROLE ON access_user_role_assignment_role(`role`);

-- changeset auto.generated:1825492372-116
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_ROLE_USER_ROLE_ASSIGNMENT ON access_user_role_assignment_role(user_role_assignment);

-- changeset auto.generated:1825492372-117
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_TRUSTED_CLIENTS ON access_user_role_assignment(trusted_client);

-- changeset auto.generated:1825492372-118
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_USER ON access_user_role_assignment(user);

-- changeset auto.generated:1825492372-119
CREATE INDEX FL_USER_TENANT ON access_user(tenant);

-- changeset auto.generated:1825492372-120
CREATE INDEX FL_USER_WEBAUTHN_CREDENTIAL_USER ON access_user_webauthn_credential(user);

-- changeset auto.generated:1825492372-121
CREATE INDEX ST_DCR_INITIAL_ACCESS_TOKEN_DESCRIPTION_DESC ON access_dcr_initial_access_token(`description` DESC);

-- changeset auto.generated:1825492372-122
CREATE INDEX ST_ROLE_NAME_DESC ON access_role(name DESC);

-- changeset auto.generated:1825492372-123
CREATE INDEX ST_SNIPPET_ASSET_CODE_ASC ON document_snippet_asset(code);

-- changeset auto.generated:1825492372-124
CREATE INDEX ST_SNIPPET_ASSET_CODE_DESC ON document_snippet_asset(code DESC);

-- changeset auto.generated:1825492372-125
CREATE INDEX ST_SNIPPET_CODE_ASC ON document_snippet(code);

-- changeset auto.generated:1825492372-126
CREATE INDEX ST_SNIPPET_CODE_DESC ON document_snippet(code DESC);

-- changeset auto.generated:1825492372-127
CREATE INDEX ST_TEMPLATE_ASSET_CODE_DESC ON document_template_asset(code DESC);

-- changeset auto.generated:1825492372-128
CREATE INDEX ST_TEMPLATE_CODE_DESC ON document_template(code DESC);

-- changeset auto.generated:1825492372-129
CREATE INDEX ST_TEMPLATE_VARIABLE_CODE_ASC ON document_template_variable(code);

-- changeset auto.generated:1825492372-130
CREATE INDEX ST_TEMPLATE_VARIABLE_CODE_DESC ON document_template_variable(code DESC);

-- changeset auto.generated:1825492372-131
CREATE INDEX ST_TENANT_LOGIN_PROVIDER_NAME_DESC ON access_tenant_login_provider(name DESC);

-- changeset auto.generated:1825492372-132
CREATE INDEX ST_THEME_ASSET_CODE_DESC ON document_theme_asset(code DESC);

-- changeset auto.generated:1825492372-133
CREATE INDEX ST_THEME_NAME_ASC ON document_theme(name);

-- changeset auto.generated:1825492372-134
CREATE INDEX ST_THEME_NAME_DESC ON document_theme(name DESC);

-- changeset auto.generated:1825492372-135
CREATE INDEX ST_TRUSTED_CLIENT_ALLOWED_AUDIENCE_AUDIENCE_ASC ON access_trusted_client_allowed_audience(audience);

-- changeset auto.generated:1825492372-136
CREATE INDEX ST_TRUSTED_CLIENT_ALLOWED_AUDIENCE_AUDIENCE_DESC ON access_trusted_client_allowed_audience(audience DESC);

-- changeset auto.generated:1825492372-137
CREATE INDEX ST_TRUSTED_CLIENT_CODE_DESC ON access_trusted_client(code DESC);

-- changeset auto.generated:1825492372-138
CREATE INDEX ST_USER_INVITATION_CREATED_AT_DESC ON access_user_invitation(created_at DESC);

-- changeset auto.generated:1825492372-139
CREATE INDEX ST_USER_NAME_DESC ON access_user(name DESC);

-- changeset auto.generated:1825492372-140
CREATE INDEX ST_USER_PERSONAL_API_KEY_NAME_ASC ON access_user_personal_api_key(name);

-- changeset auto.generated:1825492372-141
CREATE INDEX ST_USER_PERSONAL_API_KEY_NAME_DESC ON access_user_personal_api_key(name DESC);

-- changeset auto.generated:1825492372-142
CREATE UNIQUE INDEX UK_CONSENT_PURPOSE_TENANT_TITLE ON access_consent_purpose(tenant, title);

-- changeset auto.generated:1825492372-143
CREATE UNIQUE INDEX UK_ROLE_RELYING_PARTY_NAME ON access_role(relying_party, name);

-- changeset auto.generated:1825492372-144
CREATE UNIQUE INDEX UK_SNIPPET_ASSET_CODE_SNIPPET ON document_snippet_asset(code, snippet);

-- changeset auto.generated:1825492372-145
CREATE UNIQUE INDEX UK_SNIPPET_CODE_TENANT ON document_snippet(code, tenant);

-- changeset auto.generated:1825492372-146
CREATE UNIQUE INDEX UK_TEMPLATE_ASSET_CODE_TEMPLATE_TENANT ON document_template_asset(code, template, tenant);

-- changeset auto.generated:1825492372-147
CREATE UNIQUE INDEX UK_TEMPLATE_CODE_TENANT ON document_template(code, tenant);

-- changeset auto.generated:1825492372-148
CREATE UNIQUE INDEX UK_TEMPLATE_VARIABLE_CODE_TENANT ON document_template_variable(code, tenant);

-- changeset auto.generated:1825492372-149
CREATE UNIQUE INDEX UK_TENANT_LOGIN_PROVIDER_TENANT_NAME ON access_tenant_login_provider(tenant, name);

-- changeset auto.generated:1825492372-150
CREATE UNIQUE INDEX UK_THEME_ASSET_CODE_THEME ON document_theme_asset(code, theme);

-- changeset auto.generated:1825492372-151
CREATE UNIQUE INDEX UK_THEME_NAME_TENANT ON document_theme(name, tenant);

-- changeset auto.generated:1825492372-152
CREATE UNIQUE INDEX UK_TRUSTED_CLIENT_ALLOWED_AUDIENCE_CLIENT_AUDIENCE ON access_trusted_client_allowed_audience(client, audience);

-- changeset auto.generated:1825492372-153
CREATE UNIQUE INDEX UK_TRUSTED_CLIENT_TENANT_CODE ON access_trusted_client(tenant, code);

-- changeset auto.generated:1825492372-154
CREATE UNIQUE INDEX UK_USER_ACCEPTED_TERMS_OF_USE_USER_CONDITIONS ON access_user_accepted_terms_of_use(user, conditions);

-- changeset auto.generated:1825492372-155
CREATE UNIQUE INDEX UK_USER_ROLE_ASSIGNMENT_ROLE_ROLE_USER_ROLE_ASSIGNMENT ON access_user_role_assignment_role(`role`, user_role_assignment);

-- changeset auto.generated:1825492372-156
CREATE UNIQUE INDEX UK_USER_TENANT_NAME ON access_user(tenant, name);

-- changeset auto.generated:1825492372-157
CREATE UNIQUE INDEX UK_USER_WEBAUTHN_CREDENTIAL_USER_NAME ON access_user_webauthn_credential(user, name);

-- changeset auto.generated:1825492372-158
CREATE INDEX fk_audit_change_action ON _audit_change(action_id);

-- changeset auto.generated:1825492372-159
CREATE INDEX idx_audit_action_actor ON _audit_action(actor_id);

-- changeset auto.generated:1825492372-160
CREATE INDEX idx_audit_action_occurred_at ON _audit_action(occurred_at);

-- changeset auto.generated:1825492372-161
CREATE INDEX idx_audit_change_target ON _audit_change(target_type, target_id);

-- changeset auto.generated:1825492372-162
CREATE INDEX idx_challenge_expires ON _oauth_webauthn_challenge(expires_at);

-- changeset auto.generated:1825492372-163
CREATE INDEX idx_device_client ON _oauth_device_codes(client_id);

-- changeset auto.generated:1825492372-164
CREATE INDEX idx_device_expires ON _oauth_device_codes(expires_at);

-- changeset auto.generated:1825492372-165
CREATE INDEX idx_device_status ON _oauth_device_codes(status);

-- changeset auto.generated:1825492372-166
CREATE INDEX idx_entity_changelog_ordering ON _entity_changelog(changed_at, entity_id);

-- changeset auto.generated:1825492372-167
CREATE INDEX idx_entity_changelog_type ON _entity_changelog(entity_type);

-- changeset auto.generated:1825492372-168
CREATE INDEX idx_magic_link_expires ON _oauth_magic_link(expires_at);

-- changeset auto.generated:1825492372-169
CREATE INDEX idx_name ON _prometheus__summaries(name);

-- changeset auto.generated:1825492372-170
CREATE INDEX idx_oauth_audit_created ON _oauth_audit_log(created_at);

-- changeset auto.generated:1825492372-171
CREATE INDEX idx_oauth_audit_session ON _oauth_audit_log(session_id);

-- changeset auto.generated:1825492372-172
CREATE INDEX idx_oauth_audit_type ON _oauth_audit_log(event_type, tenant_id, created_at);

-- changeset auto.generated:1825492372-173
CREATE INDEX idx_oauth_audit_user ON _oauth_audit_log(user_id, tenant_id, created_at);

-- changeset auto.generated:1825492372-174
CREATE INDEX idx_oauth_delegated_state_expires ON _oauth_delegated_state(expires_at);

-- changeset auto.generated:1825492372-175
CREATE INDEX idx_oauth_session_expiration ON _oauth_session(expiration);

-- changeset auto.generated:1825492372-176
CREATE INDEX idx_oauth_session_expiration ON _oauth_temporal_codes(expiration);

-- changeset auto.generated:1825492372-177
CREATE INDEX idx_oauth_session_user_uid ON _oauth_magic_link(user_uid);

-- changeset auto.generated:1825492372-178
CREATE INDEX idx_par_expires ON _oauth_par_request(expires_at);

-- changeset auto.generated:1825492372-179
CREATE INDEX idx_par_tenant ON _oauth_par_request(tenant_id);

-- changeset auto.generated:1825492372-180
CREATE INDEX idx_published_at ON _output_queue_pending_events(published_at);

-- changeset auto.generated:1825492372-181
CREATE INDEX idx_revoked_expires ON _oauth_revoked_jti(expires_at);

-- changeset auto.generated:1825492372-182
CREATE INDEX idx_revoked_family_expires ON _oauth_revoked_refresh_family(expires_at);

-- changeset auto.generated:1825492372-183
CREATE INDEX idx_revoked_tenant ON _oauth_revoked_jti(tenant_id);

-- changeset auto.generated:1825492372-184
CREATE INDEX idx_session_token_exp ON _oauth_session_token(expires_at);

-- changeset auto.generated:1825492372-185
CREATE INDEX idx_session_token_session ON _oauth_session_token(session);

-- changeset auto.generated:1825492372-186
CREATE INDEX idx_status_next_retry ON _output_queue_pending_events(status, next_retry);

-- changeset auto.generated:1825492372-187
CREATE UNIQUE INDEX uq_device_user_code ON _oauth_device_codes(tenant, user_code);

-- changeset auto.generated:1825492372-188
ALTER TABLE access_consent_purpose ADD CONSTRAINT FK_ACCESS_CONSENT_PURPOSE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-189
ALTER TABLE access_dcr_initial_access_token ADD CONSTRAINT FK_ACCESS_DCR_INITIAL_ACCESS_TOKEN_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-190
ALTER TABLE access_role ADD CONSTRAINT FK_ACCESS_ROLE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-191
ALTER TABLE access_tenant_config ADD CONSTRAINT FK_ACCESS_TENANT_CONFIG_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-192
ALTER TABLE access_tenant_login_provider ADD CONSTRAINT FK_ACCESS_TENANT_LOGIN_PROVIDER_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-193
ALTER TABLE access_tenant_terms_of_use ADD CONSTRAINT FK_ACCESS_TENANT_TERMS_OF_USE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-194
ALTER TABLE access_tenant_terms_of_use ADD CONSTRAINT FK_ACCESS_TENANT_TERMS_OF_USE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-195
ALTER TABLE access_trusted_client_allowed_audience ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_ALLOWED_AUDIENCE_CLIENT FOREIGN KEY (client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-196
ALTER TABLE access_trusted_client_allowed_redirect ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_ALLOWED_REDIRECT_CLIENT FOREIGN KEY (client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-197
ALTER TABLE access_trusted_client ADD CONSTRAINT FK_ACCESS_TRUSTED_CLIENT_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-198
ALTER TABLE access_user_accepted_terms_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMS_OF_USE_CONDITIONS FOREIGN KEY (conditions) REFERENCES access_tenant_terms_of_use (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-199
ALTER TABLE access_user_accepted_terms_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMS_OF_USE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-200
ALTER TABLE access_user_access_temporal_code ADD CONSTRAINT FK_ACCESS_USER_ACCESS_TEMPORAL_CODE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-201
ALTER TABLE access_user_consented_scopes ADD CONSTRAINT FK_ACCESS_USER_CONSENTED_SCOPES_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-202
ALTER TABLE access_user_consented_scopes ADD CONSTRAINT FK_ACCESS_USER_CONSENTED_SCOPES_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-203
ALTER TABLE access_user_consent_purposes ADD CONSTRAINT FK_ACCESS_USER_CONSENT_PURPOSES_CONSENT_PURPOSE FOREIGN KEY (consent_purpose) REFERENCES access_consent_purpose (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-204
ALTER TABLE access_user_consent_purposes ADD CONSTRAINT FK_ACCESS_USER_CONSENT_PURPOSES_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-205
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-206
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-207
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-208
ALTER TABLE access_user_invitation ADD CONSTRAINT FK_ACCESS_USER_INVITATION_ACCEPTED_BY FOREIGN KEY (accepted_by) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-209
ALTER TABLE access_user_invitation ADD CONSTRAINT FK_ACCESS_USER_INVITATION_INVITED_BY FOREIGN KEY (invited_by) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-210
ALTER TABLE access_user_invitation ADD CONSTRAINT FK_ACCESS_USER_INVITATION_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-211
ALTER TABLE access_user_mfa_recovery_code ADD CONSTRAINT FK_ACCESS_USER_MFA_RECOVERY_CODE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-212
ALTER TABLE access_user_personal_api_key ADD CONSTRAINT FK_ACCESS_USER_PERSONAL_API_KEY_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-213
ALTER TABLE access_user_profile ADD CONSTRAINT FK_ACCESS_USER_PROFILE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-214
ALTER TABLE access_user_role_assignment ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-215
ALTER TABLE access_user_role_assignment_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_ROLE_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-216
ALTER TABLE access_user_role_assignment_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_ROLE_USER_ROLE_ASSIGNMENT FOREIGN KEY (user_role_assignment) REFERENCES access_user_role_assignment (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-217
ALTER TABLE access_user_role_assignment ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-218
ALTER TABLE access_user_role_assignment ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-219
ALTER TABLE access_user ADD CONSTRAINT FK_ACCESS_USER_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-220
ALTER TABLE access_user_webauthn_credential ADD CONSTRAINT FK_ACCESS_USER_WEBAUTHN_CREDENTIAL_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-221
ALTER TABLE document_snippet_asset ADD CONSTRAINT FK_DOCUMENT_SNIPPET_ASSET_SNIPPET FOREIGN KEY (snippet) REFERENCES document_snippet (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-222
ALTER TABLE document_snippet ADD CONSTRAINT FK_DOCUMENT_SNIPPET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-223
ALTER TABLE document_snippet_version ADD CONSTRAINT FK_DOCUMENT_SNIPPET_VERSION_SNIPPET FOREIGN KEY (snippet) REFERENCES document_snippet (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-224
ALTER TABLE document_template_asset ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_ASSET_TEMPLATE FOREIGN KEY (template) REFERENCES document_template (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-225
ALTER TABLE document_template_asset ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_ASSET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-226
ALTER TABLE document_template ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-227
ALTER TABLE document_template_variable ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_VARIABLE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-228
ALTER TABLE document_template_version ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_VERSION_TEMPLATE FOREIGN KEY (template) REFERENCES document_template (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-229
ALTER TABLE document_theme_asset ADD CONSTRAINT FK_DOCUMENT_THEME_ASSET_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-230
ALTER TABLE document_theme ADD CONSTRAINT FK_DOCUMENT_THEME_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-231
ALTER TABLE document_theme_version ADD CONSTRAINT FK_DOCUMENT_THEME_VERSION_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-232
ALTER TABLE notification_message ADD CONSTRAINT FK_NOTIFICATION_MESSAGE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-233
ALTER TABLE notification_smtp_outbound_config ADD CONSTRAINT FK_NOTIFICATION_SMTP_OUTBOUND_CONFIG_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-234
ALTER TABLE _oauth_session_grant ADD CONSTRAINT FK__OAUTH_SESSION_GRANT_SESSION FOREIGN KEY (session) REFERENCES _oauth_session (session) ON UPDATE CASCADE ON DELETE CASCADE;

-- changeset auto.generated:1825492372-235
ALTER TABLE _oauth_session_token ADD CONSTRAINT FK__OAUTH_SESSION_TOKEN_GRANT FOREIGN KEY (grant_id) REFERENCES _oauth_session_grant (id) ON UPDATE RESTRICT ON DELETE SET NULL;

-- changeset auto.generated:1825492372-236
ALTER TABLE _oauth_session_token ADD CONSTRAINT FK__OAUTH_SESSION_TOKEN_SESSION FOREIGN KEY (session) REFERENCES _oauth_session (session) ON UPDATE CASCADE ON DELETE CASCADE;

-- changeset auto.generated:1825492372-237
ALTER TABLE _audit_change ADD CONSTRAINT fk_audit_change_action FOREIGN KEY (action_id) REFERENCES _audit_action (id) ON UPDATE RESTRICT ON DELETE CASCADE;

