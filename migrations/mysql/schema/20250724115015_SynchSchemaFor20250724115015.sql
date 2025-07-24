--liquibase formatted sql

--changeset auto.generated:1825492372-2
CREATE TABLE access_scope_attribute_permission (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `read` BIT(1) NOT NULL, `write` BIT(1) NOT NULL, security_attribute VARCHAR(255) NOT NULL, security_domain VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_SCOPE_ATTRIBUTE_PERMISSION PRIMARY KEY (uid));

--changeset auto.generated:1825492372-3
CREATE TABLE access_security_attribute (uid VARCHAR(255) NOT NULL, version INT NOT NULL, attribute VARCHAR(255) NOT NULL, enabled BIT(1) DEFAULT 0 NULL, read_visibility VARCHAR(255) NULL, `resource` VARCHAR(255) NOT NULL, write_visibility VARCHAR(255) NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, CONSTRAINT PK_ACCESS_SECURITY_ATTRIBUTE PRIMARY KEY (uid));

--changeset auto.generated:1825492372-4
CREATE INDEX FL_SCOPE_ATTRIBUTE_PERMISSION_SECURITY_ATTRIBUTE ON access_scope_attribute_permission(security_attribute);

--changeset auto.generated:1825492372-5
CREATE INDEX FL_SCOPE_ATTRIBUTE_PERMISSION_SECURITY_DOMAINS ON access_scope_attribute_permission(security_domain);

--changeset auto.generated:1825492372-6
CREATE INDEX FL_SECURITY_ATTRIBUTE_RELYING_PARTYS ON access_security_attribute(relying_party);

--changeset auto.generated:1825492372-7
CREATE INDEX FL_SECURITY_ATTRIBUTE_RESOURCE ON access_security_attribute(`resource`);

--changeset auto.generated:1825492372-8
CREATE INDEX FL_SECURITY_ATTRIBUTE_TRUSTED_CLIENT ON access_security_attribute(trusted_client);

--changeset auto.generated:1825492372-9
CREATE UNIQUE INDEX UK_SCOPE_ATTRIBUTE_PERMISSION_SECURITY_DOMAIN_SECURITY_ATTRIBUTE ON access_scope_attribute_permission(security_domain, security_attribute);

--changeset auto.generated:1825492372-10
CREATE UNIQUE INDEX UK_SECURITY_ATTRIBUTE_RESOURCE_ATTRIBUTE ON access_security_attribute(`resource`, attribute);

--changeset auto.generated:1825492372-11
ALTER TABLE access_scope_attribute_permission ADD CONSTRAINT FK_ACCESS_SCOPE_ATTRIBUTE_PERMISSION_SECURITY_ATTRIBUTE FOREIGN KEY (security_attribute) REFERENCES access_security_attribute (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-12
ALTER TABLE access_scope_attribute_permission ADD CONSTRAINT FK_ACCESS_SCOPE_ATTRIBUTE_PERMISSION_SECURITY_DOMAIN FOREIGN KEY (security_domain) REFERENCES access_security_domain (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-13
ALTER TABLE access_security_attribute ADD CONSTRAINT FK_ACCESS_SECURITY_ATTRIBUTE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-14
ALTER TABLE access_security_attribute ADD CONSTRAINT FK_ACCESS_SECURITY_ATTRIBUTE_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

