--liquibase formatted sql

--changeset auto.generated:1825492372-1
CREATE TABLE access_tenant_party_security (uid VARCHAR(255) NOT NULL, version INT NOT NULL, policies LONGTEXT NULL, relying_party VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_TENANT_PARTY_SECURITY PRIMARY KEY (uid));

--changeset auto.generated:1825492372-2
CREATE INDEX FL_TENANT_PARTY_SECURITY_RELYING_PARTYS ON access_tenant_party_security(relying_party);

--changeset auto.generated:1825492372-3
CREATE INDEX FL_TENANT_PARTY_SECURITY_TENANT ON access_tenant_party_security(tenant);

--changeset auto.generated:1825492372-4
CREATE UNIQUE INDEX UK_TENANT_PARTY_SECURITY_TENANT_RELYING_PARTY ON access_tenant_party_security(tenant, relying_party);

--changeset auto.generated:1825492372-5
ALTER TABLE access_tenant_party_security ADD CONSTRAINT FK_ACCESS_TENANT_PARTY_SECURITY_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

--changeset auto.generated:1825492372-6
ALTER TABLE access_tenant_party_security ADD CONSTRAINT FK_ACCESS_TENANT_PARTY_SECURITY_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

