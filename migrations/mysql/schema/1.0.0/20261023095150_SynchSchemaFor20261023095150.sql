-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE access_user_federated_identity (uid VARCHAR(255) NOT NULL, version INT NOT NULL, created_at timestamp DEFAULT NULL NULL, subject VARCHAR(255) NOT NULL, login_provider VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_FEDERATED_IDENTITY PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-2
CREATE INDEX FL_USER_FEDERATED_IDENTITY_LOGIN_PROVIDERS ON access_user_federated_identity(login_provider);

-- changeset auto.generated:1825492372-3
CREATE INDEX FL_USER_FEDERATED_IDENTITY_USERS ON access_user_federated_identity(user);

-- changeset auto.generated:1825492372-4
CREATE INDEX ST_USER_FEDERATED_IDENTITY_SUBJECT_ASC ON access_user_federated_identity(subject);

-- changeset auto.generated:1825492372-5
CREATE INDEX ST_USER_FEDERATED_IDENTITY_SUBJECT_DESC ON access_user_federated_identity(subject DESC);

-- changeset auto.generated:1825492372-6
CREATE UNIQUE INDEX UK_USER_FEDERATED_IDENTITY_LOGIN_PROVIDER_SUBJECT ON access_user_federated_identity(login_provider, subject);

-- changeset auto.generated:1825492372-7
ALTER TABLE access_user_federated_identity ADD CONSTRAINT FK_ACCESS_USER_FEDERATED_IDENTITY_LOGIN_PROVIDER FOREIGN KEY (login_provider) REFERENCES access_tenant_login_provider (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-8
ALTER TABLE access_user_federated_identity ADD CONSTRAINT FK_ACCESS_USER_FEDERATED_IDENTITY_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

