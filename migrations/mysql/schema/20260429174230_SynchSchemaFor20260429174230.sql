-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
ALTER TABLE _oauth_session MODIFY user_agent VARCHAR(250);

-- changeset auto.generated:1825492372-2
CREATE TABLE access_consent_purpose (uid VARCHAR(255) NOT NULL, version INT NOT NULL, activation_date timestamp DEFAULT NULL NULL, `description` LONGTEXT NOT NULL, `key` VARCHAR(255) NOT NULL, required BIT NOT NULL, title VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_CONSENT_PURPOSE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-3
CREATE TABLE access_user_consent_purposes (uid VARCHAR(255) NOT NULL, version INT NOT NULL, decision_at timestamp DEFAULT NULL NULL, granted BIT NOT NULL, ip_address VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, consent_purpose VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_CONSENT_PURPOSES PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-4
ALTER TABLE access_user_accepted_termns_of_use ADD ip_address VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-5
ALTER TABLE access_user_consented_scopes ADD ip_address VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-6
ALTER TABLE access_user_accepted_termns_of_use ADD user_agent VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-7
ALTER TABLE access_user_consented_scopes ADD user_agent VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-8
CREATE INDEX FL_CONSENT_PURPOSE_TENANT ON access_consent_purpose(tenant);

-- changeset auto.generated:1825492372-9
CREATE INDEX FL_USER_CONSENT_PURPOSES_CONSENT_PURPOSES ON access_user_consent_purposes(consent_purpose);

-- changeset auto.generated:1825492372-10
CREATE INDEX FL_USER_CONSENT_PURPOSES_USERS ON access_user_consent_purposes(user);

-- changeset auto.generated:1825492372-11
CREATE UNIQUE INDEX UK_CONSENT_PURPOSE_TENANT_TITLE ON access_consent_purpose(tenant, title);

-- changeset auto.generated:1825492372-12
ALTER TABLE access_consent_purpose ADD CONSTRAINT FK_ACCESS_CONSENT_PURPOSE_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-13
ALTER TABLE access_user_consent_purposes ADD CONSTRAINT FK_ACCESS_USER_CONSENT_PURPOSES_CONSENT_PURPOSE FOREIGN KEY (consent_purpose) REFERENCES access_consent_purpose (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-14
ALTER TABLE access_user_consent_purposes ADD CONSTRAINT FK_ACCESS_USER_CONSENT_PURPOSES_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

