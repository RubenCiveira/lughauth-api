-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client MODIFY client_name VARCHAR(255);

-- changeset auto.generated:1825492372-2
ALTER TABLE access_trusted_client MODIFY client_uri VARCHAR(255);

-- changeset auto.generated:1825492372-3
ALTER TABLE access_tenant_config MODIFY dynamic_registration_policy VARCHAR(255);

-- changeset auto.generated:1825492372-5
ALTER TABLE access_tenant_config ALTER dynamic_registration_policy SET DEFAULT null;

-- changeset auto.generated:1825492372-6
ALTER TABLE access_trusted_client MODIFY dynamically_registered BIT(1);

-- changeset auto.generated:1825492372-8
ALTER TABLE access_trusted_client ALTER dynamically_registered SET DEFAULT 0;

-- changeset auto.generated:1825492372-9
ALTER TABLE access_trusted_client MODIFY grant_types_json LONGTEXT;

-- changeset auto.generated:1825492372-10
ALTER TABLE access_trusted_client MODIFY logo_uri VARCHAR(255);

-- changeset auto.generated:1825492372-11
ALTER TABLE access_trusted_client MODIFY policy_uri VARCHAR(255);

-- changeset auto.generated:1825492372-12
ALTER TABLE access_trusted_client MODIFY registered_at timestamp;

-- changeset auto.generated:1825492372-13
ALTER TABLE access_trusted_client MODIFY registration_access VARCHAR(255);

-- changeset auto.generated:1825492372-14
ALTER TABLE access_trusted_client MODIFY response_types_json VARCHAR(255);

-- changeset auto.generated:1825492372-15
ALTER TABLE access_trusted_client MODIFY token_endpoint_auth_method VARCHAR(255);

-- changeset auto.generated:1825492372-17
ALTER TABLE access_trusted_client MODIFY tos_uri VARCHAR(255);

