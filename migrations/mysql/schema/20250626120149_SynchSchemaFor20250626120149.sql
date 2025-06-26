--liquibase formatted sql

--changeset auto.generated:1825492372-1
ALTER TABLE access_tenant_config ADD allow_recover_pass BIT(1) DEFAULT 0 NULL;

--changeset auto.generated:1825492372-2
ALTER TABLE access_tenant_config ADD allow_register BIT(1) DEFAULT 0 NULL;

--changeset auto.generated:1825492372-3
ALTER TABLE access_tenant_config ADD disabled_user_email LONGTEXT NULL;

--changeset auto.generated:1825492372-4
ALTER TABLE access_tenant_config ADD enable_register_users BIT(1) DEFAULT 0 NULL;

--changeset auto.generated:1825492372-5
ALTER TABLE access_user_access_temporal_code ADD register_code VARCHAR(255) NULL;

--changeset auto.generated:1825492372-6
ALTER TABLE access_tenant_config ADD enabled_user_email LONGTEXT NULL;

--changeset auto.generated:1825492372-7
ALTER TABLE access_tenant_config ADD recover_pass_email LONGTEXT NULL;

--changeset auto.generated:1825492372-8
ALTER TABLE access_tenant_config ADD registerd_email LONGTEXT NULL;

--changeset auto.generated:1825492372-9
ALTER TABLE access_user ADD wellcome_at timestamp DEFAULT null NULL;

--changeset auto.generated:1825492372-10
ALTER TABLE access_tenant_config ADD wellcome_email LONGTEXT NULL;

--changeset auto.generated:1825492372-11
CREATE UNIQUE INDEX UK_USER_ACCESS_TEMPORAL_CODE_REGISTER_CODE_UNIQUE ON access_user_access_temporal_code(register_code);

