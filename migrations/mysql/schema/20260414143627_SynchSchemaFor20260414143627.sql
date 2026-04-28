-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client ADD back_channel_logout_session_required BIT DEFAULT 0 NULL;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_trusted_client ADD back_channel_logout_uri VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-3
ALTER TABLE access_trusted_client ADD front_channel_logout_session_required BIT DEFAULT 0 NULL;

-- changeset auto.generated:1825492372-4
ALTER TABLE access_trusted_client ADD front_channel_logout_uri VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-5
CREATE INDEX FL_TRUSTED_CLIENT_WITH_BACK_CHANNEL_URL ON access_trusted_client(back_channel_logout_uri);

-- changeset auto.generated:1825492372-6
CREATE INDEX FL_TRUSTED_CLIENT_WITH_FRONT_CHANNEL_URL ON access_trusted_client(front_channel_logout_uri);

