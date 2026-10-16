-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE UNIQUE INDEX uq_oauth_temporal_codes_code ON _oauth_temporal_codes(code);

