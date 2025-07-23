--liquibase formatted sql

--changeset auto.generated:1825492372-1
CREATE TABLE access_role_domain (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `role` VARCHAR(255) NOT NULL, security_domain VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_ROLE_DOMAIN PRIMARY KEY (uid));

--changeset auto.generated:1825492372-2
CREATE TABLE access_scope_assignation (uid VARCHAR(255) NOT NULL, version INT NOT NULL, security_domain VARCHAR(255) NOT NULL, security_scope VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_SCOPE_ASSIGNATION PRIMARY KEY (uid));

--changeset auto.generated:1825492372-3
CREATE TABLE access_security_domain (uid VARCHAR(255) NOT NULL, version INT NOT NULL, enabled BIT(1) DEFAULT 0 NULL, level INT NOT NULL, manage_all BIT(1) DEFAULT 0 NULL, name VARCHAR(255) NOT NULL, read_all BIT(1) DEFAULT 0 NULL, write_all BIT(1) DEFAULT 0 NULL, CONSTRAINT PK_ACCESS_SECURITY_DOMAIN PRIMARY KEY (uid), UNIQUE (name));

--changeset auto.generated:1825492372-4
CREATE TABLE access_security_scope (uid VARCHAR(255) NOT NULL, version INT NOT NULL, enabled BIT(1) DEFAULT 0 NULL, kind VARCHAR(255) NULL, `resource` VARCHAR(255) NOT NULL, scope VARCHAR(255) NOT NULL, visibility VARCHAR(255) NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_SECURITY_SCOPE PRIMARY KEY (uid));

--changeset auto.generated:1825492372-5
CREATE INDEX FL_ROLE_DOMAIN_ROLE ON access_role_domain(`role`);

--changeset auto.generated:1825492372-6
CREATE INDEX FL_ROLE_DOMAIN_SECURITY_DOMAIN ON access_role_domain(security_domain);

--changeset auto.generated:1825492372-7
CREATE INDEX FL_SCOPE_ASSIGNATION_SECURITY_DOMAIN ON access_scope_assignation(security_domain);

--changeset auto.generated:1825492372-8
CREATE INDEX FL_SCOPE_ASSIGNATION_SECURITY_SCOPES ON access_scope_assignation(security_scope);

--changeset auto.generated:1825492372-9
CREATE INDEX FL_SECURITY_DOMAIN_ENABLED ON access_security_domain(enabled);

--changeset auto.generated:1825492372-10
CREATE INDEX FL_SECURITY_SCOPE_RELYING_PARTY ON access_security_scope(relying_party);

--changeset auto.generated:1825492372-11
CREATE INDEX FL_SECURITY_SCOPE_RESOURCE ON access_security_scope(`resource`);

--changeset auto.generated:1825492372-12
CREATE INDEX FL_SECURITY_SCOPE_TRUSTED_CLIENTS ON access_security_scope(trusted_client);

--changeset auto.generated:1825492372-13
CREATE UNIQUE INDEX UK_ROLE_DOMAIN_ROLE_SECURITY_DOMAIN ON access_role_domain(`role`, security_domain);

--changeset auto.generated:1825492372-14
CREATE UNIQUE INDEX UK_SCOPE_ASSIGNATION_SECURITY_DOMAIN_SECURITY_SCOPE ON access_scope_assignation(security_domain, security_scope);

--changeset auto.generated:1825492372-15
CREATE UNIQUE INDEX UK_SECURITY_SCOPE_RESOURCE_SCOPE ON access_security_scope(`resource`, scope);

--changeset auto.generated:1825492372-16
CREATE UNIQUE INDEX UK_USER_IDENTITY_USER_RELYING_PARTY_TRUSTED_CLIENT ON access_user_identity(user, relying_party, trusted_client);

--changeset auto.generated:1825492372-17
ALTER TABLE access_role_domain ADD CONSTRAINT FK_ACCESS_ROLE_DOMAIN_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-18
ALTER TABLE access_role_domain ADD CONSTRAINT FK_ACCESS_ROLE_DOMAIN_SECURITY_DOMAIN FOREIGN KEY (security_domain) REFERENCES access_security_domain (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-19
ALTER TABLE access_scope_assignation ADD CONSTRAINT FK_ACCESS_SCOPE_ASSIGNATION_SECURITY_DOMAIN FOREIGN KEY (security_domain) REFERENCES access_security_domain (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-20
ALTER TABLE access_scope_assignation ADD CONSTRAINT FK_ACCESS_SCOPE_ASSIGNATION_SECURITY_SCOPE FOREIGN KEY (security_scope) REFERENCES access_security_scope (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-21
ALTER TABLE access_security_scope ADD CONSTRAINT FK_ACCESS_SECURITY_SCOPE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-22
ALTER TABLE access_security_scope ADD CONSTRAINT FK_ACCESS_SECURITY_SCOPE_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

