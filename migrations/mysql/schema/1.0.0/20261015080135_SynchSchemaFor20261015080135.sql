-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE access_user_accepted_terms_of_use (uid VARCHAR(255) NOT NULL, version INT NOT NULL, accept_date timestamp DEFAULT NULL NULL, ip_address VARCHAR(255) NULL, user_agent VARCHAR(255) NULL, conditions VARCHAR(255) NOT NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ACCEPTED_TERMS_OF_USE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-2
CREATE TABLE access_user_role_assignment (uid VARCHAR(255) NOT NULL, version INT NOT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNMENT PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-3
CREATE TABLE access_user_role_assignment_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, `role` VARCHAR(255) NOT NULL, user_role_assignment VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_ROLE_ASSIGNMENT_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-4
ALTER TABLE access_user_webauthn_credential ADD authenticator VARCHAR(255) NOT NULL;

-- changeset auto.generated:1825492372-5
ALTER TABLE access_tenant_login_provider ADD saml_idp_cert VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-6
ALTER TABLE access_user ADD welcome_at timestamp DEFAULT null NULL;

-- changeset auto.generated:1825492372-7
ALTER TABLE access_tenant_config ADD registered_email LONGTEXT NULL;

-- changeset auto.generated:1825492372-8
ALTER TABLE access_tenant_config ADD welcome_email LONGTEXT NULL;

-- changeset auto.generated:1825492372-9
CREATE INDEX FL_USER_ACCEPTED_TERMS_OF_USE_CONDITIONS ON access_user_accepted_terms_of_use(conditions);

-- changeset auto.generated:1825492372-10
CREATE INDEX FL_USER_ACCEPTED_TERMS_OF_USE_USERS ON access_user_accepted_terms_of_use(user);

-- changeset auto.generated:1825492372-11
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_RELYING_PARTY ON access_user_role_assignment(relying_party);

-- changeset auto.generated:1825492372-12
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_ROLE_ROLE ON access_user_role_assignment_role(`role`);

-- changeset auto.generated:1825492372-13
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_ROLE_USER_ROLE_ASSIGNMENT ON access_user_role_assignment_role(user_role_assignment);

-- changeset auto.generated:1825492372-14
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_TRUSTED_CLIENTS ON access_user_role_assignment(trusted_client);

-- changeset auto.generated:1825492372-15
CREATE INDEX FL_USER_ROLE_ASSIGNMENT_USER ON access_user_role_assignment(user);

-- changeset auto.generated:1825492372-16
CREATE UNIQUE INDEX UK_USER_ACCEPTED_TERMS_OF_USE_USER_CONDITIONS ON access_user_accepted_terms_of_use(user, conditions);

-- changeset auto.generated:1825492372-17
CREATE UNIQUE INDEX UK_USER_ROLE_ASSIGNMENT_ROLE_ROLE_USER_ROLE_ASSIGNMENT ON access_user_role_assignment_role(`role`, user_role_assignment);

-- changeset auto.generated:1825492372-18
CREATE UNIQUE INDEX UK_USER_WEBAUTHN_CREDENTIAL_AUTHENTICATOR_UNIQUE ON access_user_webauthn_credential(authenticator);

-- changeset auto.generated:1825492372-19
ALTER TABLE access_user_accepted_terms_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMS_OF_USE_CONDITIONS FOREIGN KEY (conditions) REFERENCES access_tenant_terms_of_use (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-20
ALTER TABLE access_user_accepted_terms_of_use ADD CONSTRAINT FK_ACCESS_USER_ACCEPTED_TERMS_OF_USE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-21
ALTER TABLE access_user_role_assignment ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-22
ALTER TABLE access_user_role_assignment_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_ROLE_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-23
ALTER TABLE access_user_role_assignment_role ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_ROLE_USER_ROLE_ASSIGNMENT FOREIGN KEY (user_role_assignment) REFERENCES access_user_role_assignment (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-24
ALTER TABLE access_user_role_assignment ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-25
ALTER TABLE access_user_role_assignment ADD CONSTRAINT FK_ACCESS_USER_ROLE_ASSIGNMENT_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

