-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE INDEX FL_USER_INVITATION_EMAIL ON access_user_invitation(email);

-- changeset auto.generated:1825492372-2
CREATE INDEX FL_USER_INVITATION_STATUES ON access_user_invitation(status);

-- changeset auto.generated:1825492372-3
CREATE INDEX ST_USER_INVITATION_CREATED_AT_DESC ON access_user_invitation(created_at DESC);

-- changeset auto.generated:1825492372-4
CREATE UNIQUE INDEX UK_USER_INVITATION_TOKEN_HASH_UNIQUE ON access_user_invitation(token_hash);

