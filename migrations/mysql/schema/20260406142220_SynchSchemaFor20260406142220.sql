-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE _oauth_device_codes (device_code VARCHAR(36) NOT NULL, user_code VARCHAR(16) NOT NULL, tenant VARCHAR(64) NOT NULL, client_id VARCHAR(128) NOT NULL, scope TEXT NOT NULL, audiences LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, auth_data LONGTEXT NULL, requested_at datetime NOT NULL, expires_at datetime NOT NULL, last_poll_at datetime DEFAULT NULL NULL, interval_sec INT NOT NULL, CONSTRAINT PK__OAUTH_DEVICE_CODES PRIMARY KEY (device_code));

-- changeset auto.generated:1825492372-2
CREATE TABLE access_user_group_membership (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `groups` LONGTEXT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_GROUP_MEMBERSHIP PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-3
CREATE TABLE access_user_role_assignament (uid VARCHAR(255) NOT NULL, version INT NOT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNAMENT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-4
CREATE TABLE access_user_role_assignament_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `role` VARCHAR(255) NOT NULL, user_role_assignament VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNAMENT_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-5
CREATE TABLE document_template (uid VARCHAR(255) NOT NULL, version INT NOT NULL, channel VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, tenant VARCHAR(255) NULL, theme VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-6
CREATE TABLE document_template_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-7
CREATE TABLE document_template_snippet (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content_html VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_SNIPPET PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-8
CREATE TABLE document_template_variable (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, value VARCHAR(255) NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_VARIABLE PRIMARY KEY (uid), UNIQUE (code));

-- changeset auto.generated:1825492372-9
CREATE TABLE document_template_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, subject VARCHAR(255) NULL, template VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_TEMPLATE_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-10
CREATE TABLE document_theme (uid VARCHAR(255) NOT NULL, version INT NOT NULL, custom_css VARCHAR(255) NULL, enabled BIT DEFAULT 0 NULL, is_default BIT DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-11
CREATE TABLE notification_message (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content LONGTEXT NOT NULL, created_at timestamp NOT NULL, lock_at timestamp DEFAULT NULL NULL, retries INT NOT NULL, send_at timestamp DEFAULT NULL NULL, target VARCHAR(255) NOT NULL, urgent BIT NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_NOTIFICATION_MESSAGE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-12
CREATE TABLE notification_smtp_outbound_config (uid VARCHAR(255) NOT NULL, version INT NOT NULL, host VARCHAR(255) NOT NULL, login VARCHAR(255) NOT NULL, max_retries INT NOT NULL, password VARCHAR(255) NOT NULL, port BIT NOT NULL, rate_limit INT NOT NULL, retry_delay INT NOT NULL, sender_email VARCHAR(255) NOT NULL, sender_name VARCHAR(255) NULL, timeout INT NOT NULL, use_tls BIT NOT NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_NOTIFICATION_SMTP_OUTBOUND_CONFIG PRIMARY KEY (uid), UNIQUE (tenant));

-- changeset auto.generated:1825492372-13
CREATE INDEX FL_MESSAGE_TENANT ON notification_message(tenant);

-- changeset auto.generated:1825492372-14
CREATE INDEX FL_TEMPLATE_ASSET_CODE ON document_template_asset(code);

-- changeset auto.generated:1825492372-15
CREATE INDEX FL_TEMPLATE_ASSET_TENANT ON document_template_asset(tenant);

-- changeset auto.generated:1825492372-16
CREATE INDEX FL_TEMPLATE_CODE ON document_template(code);

-- changeset auto.generated:1825492372-17
CREATE INDEX FL_TEMPLATE_SNIPPET_TENANT ON document_template_snippet(tenant);

-- changeset auto.generated:1825492372-18
CREATE INDEX FL_TEMPLATE_TENANT ON document_template(tenant);

-- changeset auto.generated:1825492372-19
CREATE INDEX FL_TEMPLATE_THEMES ON document_template(theme);

-- changeset auto.generated:1825492372-20
CREATE INDEX FL_TEMPLATE_VARIABLE_TENANT ON document_template_variable(tenant);

-- changeset auto.generated:1825492372-21
CREATE INDEX FL_TEMPLATE_VERSION_TEMPLATES ON document_template_version(template);

-- changeset auto.generated:1825492372-22
CREATE INDEX FL_THEME_TENANTS ON document_theme(tenant);

-- changeset auto.generated:1825492372-23
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_RELYING_PARTY ON access_user_group_membership(relying_party);

-- changeset auto.generated:1825492372-24
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_TRUSTED_CLIENT ON access_user_group_membership(trusted_client);

-- changeset auto.generated:1825492372-25
CREATE INDEX FL_USER_GROUP_MEMBERSHIP_USERS ON access_user_group_membership(user);

-- changeset auto.generated:1825492372-26
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_RELYING_PARTY ON access_user_role_assignament(relying_party);

-- changeset auto.generated:1825492372-27
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_ROLE_ROLE ON access_user_role_assignament_role(`role`);

-- changeset auto.generated:1825492372-28
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_ROLE_USER_ROLE_ASSIGNAMENTS ON access_user_role_assignament_role(user_role_assignament);

-- changeset auto.generated:1825492372-29
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_TRUSTED_CLIENT ON access_user_role_assignament(trusted_client);

-- changeset auto.generated:1825492372-30
CREATE INDEX FL_USER_ROLE_ASSIGNAMENT_USERS ON access_user_role_assignament(user);

-- changeset auto.generated:1825492372-31
CREATE INDEX ST_TEMPLATE_ASSET_CODE_DESC ON document_template_asset(code DESC);

-- changeset auto.generated:1825492372-32
CREATE INDEX ST_TEMPLATE_CODE_DESC ON document_template(code DESC);

-- changeset auto.generated:1825492372-33
CREATE INDEX ST_THEME_NAME_ASC ON document_theme(name);

-- changeset auto.generated:1825492372-34
CREATE INDEX ST_THEME_NAME_DESC ON document_theme(name DESC);

-- changeset auto.generated:1825492372-35
CREATE UNIQUE INDEX UK_TEMPLATE_ASSET_CODE_TENANT ON document_template_asset(code, tenant);

-- changeset auto.generated:1825492372-36
CREATE UNIQUE INDEX UK_TEMPLATE_CODE_TENANT ON document_template(code, tenant);

-- changeset auto.generated:1825492372-37
CREATE UNIQUE INDEX UK_TEMPLATE_SNIPPET_CODE_TENANT ON document_template_snippet(code, tenant);

-- changeset auto.generated:1825492372-38
CREATE UNIQUE INDEX UK_TEMPLATE_VARIABLE_CODE_TENANT ON document_template_variable(code, tenant);

-- changeset auto.generated:1825492372-39
CREATE UNIQUE INDEX UK_USER_ROLE_ASSIGNAMENT_ROLE_ROLE_USER_ROLE_ASSIGNAMENT ON access_user_role_assignament_role(`role`, user_role_assignament);

-- changeset auto.generated:1825492372-40
CREATE INDEX idx_device_client ON _oauth_device_codes(client_id);

-- changeset auto.generated:1825492372-41
CREATE INDEX idx_device_expires ON _oauth_device_codes(expires_at);

-- changeset auto.generated:1825492372-42
CREATE INDEX idx_device_status ON _oauth_device_codes(status);

-- changeset auto.generated:1825492372-43
CREATE UNIQUE INDEX uq_device_user_code ON _oauth_device_codes(tenant, user_code);

-- changeset auto.generated:1825492372-44
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-45
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-46
ALTER TABLE access_user_group_membership ADD CONSTRAINT FK_ACCESS_USER_GROUP_MEMBERSHIP_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-47
ALTER TABLE access_user_role_assignament ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-48
ALTER TABLE access_user_role_assignament_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_ROLE_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-49
ALTER TABLE access_user_role_assignament_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_ROLE_USER_ROLE_ASSIGNAMENT FOREIGN KEY (user_role_assignament) REFERENCES access_user_role_assignament (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-50
ALTER TABLE access_user_role_assignament ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-51
ALTER TABLE access_user_role_assignament ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNAMENT_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-52
ALTER TABLE document_template_asset ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_ASSET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-53
ALTER TABLE document_template_snippet ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_SNIPPET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-54
ALTER TABLE document_template ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-55
ALTER TABLE document_template ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-56
ALTER TABLE document_template_variable ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_VARIABLE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-57
ALTER TABLE document_template_version ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_VERSION_TEMPLATE FOREIGN KEY (template) REFERENCES document_template (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-58
ALTER TABLE document_theme ADD CONSTRAINT FK_DOCUMENT_THEME_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-59
ALTER TABLE notification_message ADD CONSTRAINT FK_NOTIFICATION_MESSAGE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-60
ALTER TABLE notification_smtp_outbound_config ADD CONSTRAINT FK_NOTIFICATION_SMTP_OUTBOUND_CONFIG_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

