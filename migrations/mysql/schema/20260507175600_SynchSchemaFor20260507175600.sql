-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE access_user_invitation MODIFY accepted_at timestamp;

-- changeset auto.generated:1825492372-2
ALTER TABLE access_user_invitation MODIFY accepted_by VARCHAR(255);

-- changeset auto.generated:1825492372-3
ALTER TABLE access_user_invitation MODIFY created_at timestamp;

-- changeset auto.generated:1825492372-5
ALTER TABLE access_user_invitation MODIFY email VARCHAR(255);

-- changeset auto.generated:1825492372-6
ALTER TABLE access_user_invitation MODIFY invited_by VARCHAR(255);

-- changeset auto.generated:1825492372-7
ALTER TABLE access_user_invitation MODIFY metadata_json LONGTEXT;

-- changeset auto.generated:1825492372-8
ALTER TABLE access_user_invitation MODIFY status VARCHAR(255);

-- changeset auto.generated:1825492372-10
ALTER TABLE access_user_invitation ALTER status SET DEFAULT null;

-- changeset auto.generated:1825492372-11
ALTER TABLE access_user_invitation MODIFY token_hash VARCHAR(255);

-- changeset auto.generated:1825492372-12
ALTER TABLE access_user_invitation MODIFY uid VARCHAR(255);

-- changeset auto.generated:1825492372-13
ALTER TABLE access_user_invitation ADD version INT NOT NULL;

-- changeset auto.generated:1825492372-14
ALTER TABLE access_user_invitation ADD expired_at timestamp NOT NULL;

-- changeset auto.generated:1825492372-15
ALTER TABLE access_user_invitation ADD roles VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-16
ALTER TABLE access_user_invitation ADD tenant VARCHAR(255) NOT NULL;

-- changeset auto.generated:1825492372-17
CREATE INDEX FL_USER_INVITATION_ACCEPTED_BYS ON access_user_invitation(accepted_by);

-- changeset auto.generated:1825492372-18
CREATE INDEX FL_USER_INVITATION_INVITED_BYS ON access_user_invitation(invited_by);

-- changeset auto.generated:1825492372-19
CREATE INDEX FL_USER_INVITATION_TENANT ON access_user_invitation(tenant);

-- changeset auto.generated:1825492372-20
ALTER TABLE access_user_invitation ADD CONSTRAINT FK_ACCESS_USER_INVITATION_ACCEPTED_BY FOREIGN KEY (accepted_by) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-21
ALTER TABLE access_user_invitation ADD CONSTRAINT FK_ACCESS_USER_INVITATION_INVITED_BY FOREIGN KEY (invited_by) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-22
ALTER TABLE access_user_invitation ADD CONSTRAINT FK_ACCESS_USER_INVITATION_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

