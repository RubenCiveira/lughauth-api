-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client ADD allow_all_scopes BIT DEFAULT 0 NULL;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_tenant_terms_of_use ADD relying_party VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-3
CREATE INDEX FL_TENANT_TERMS_OF_USE_RELYING_PARTY ON access_tenant_terms_of_use(relying_party);

-- changeset auto.generated:1825492372-4
ALTER TABLE access_tenant_terms_of_use ADD CONSTRAINT FK_ACCESS_TENANT_TERMS_OF_USE_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

