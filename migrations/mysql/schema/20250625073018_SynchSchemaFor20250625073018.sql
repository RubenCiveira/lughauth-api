--liquibase formatted sql

--changeset auto.generated:1825492372-1
CREATE UNIQUE INDEX UK_USER_ACCESS_TEMPORAL_CODE_RECOVERY_CODE_UNIQUE ON access_user_access_temporal_code(recovery_code);

