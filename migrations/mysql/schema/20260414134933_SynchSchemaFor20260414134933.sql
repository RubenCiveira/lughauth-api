-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_trusted_client MODIFY backchannel_logout_uri VARCHAR(255);

-- changeset auto.generated:1825492372-2
ALTER TABLE access_trusted_client MODIFY frontchannel_logout_uri VARCHAR(255);

-- changeset auto.generated:1825492372-3
CREATE INDEX FL_TRUSTED_CLIENT_WITH_BACKCHANNEL_URL ON access_trusted_client(backchannel_logout_uri);

-- changeset auto.generated:1825492372-4
CREATE INDEX FL_TRUSTED_CLIENT_WITH_FRONTCHANNEL_URL ON access_trusted_client(frontchannel_logout_uri);

