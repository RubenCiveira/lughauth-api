-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client ADD backchannel_logout_session_required BIT DEFAULT 0 NULL;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_trusted_client ADD backchannel_logout_uri VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-3
ALTER TABLE access_trusted_client ADD frontchannel_logout_session_required BIT DEFAULT 0 NULL;

-- changeset auto.generated:1825492372-4
ALTER TABLE access_trusted_client ADD frontchannel_logout_uri VARCHAR(255) NULL;

