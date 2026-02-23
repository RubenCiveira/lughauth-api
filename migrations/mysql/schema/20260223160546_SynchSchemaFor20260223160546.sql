-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE access_client_identity (uid VARCHAR(255) NOT NULL, version INT NOT NULL, roles LONGTEXT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_CLIENT_IDENTITY PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-2
CREATE TABLE access_platform_identity (uid VARCHAR(255) NOT NULL, version INT NOT NULL, relying_party VARCHAR(255) NULL, trusted_client VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_PLATFORM_IDENTITY PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-3
CREATE TABLE access_platform_identity_role (uid VARCHAR(255) NOT NULL, version INT NOT NULL, platform_identity VARCHAR(255) NOT NULL, `role` VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_PLATFORM_IDENTITY_ROLE PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-4
CREATE INDEX FL_CLIENT_IDENTITY_RELYING_PARTYS ON access_client_identity(relying_party);

-- changeset auto.generated:1825492372-5
CREATE INDEX FL_CLIENT_IDENTITY_TRUSTED_CLIENT ON access_client_identity(trusted_client);

-- changeset auto.generated:1825492372-6
CREATE INDEX FL_CLIENT_IDENTITY_USER ON access_client_identity(user);

-- changeset auto.generated:1825492372-7
CREATE INDEX FL_PLATFORM_IDENTITY_RELYING_PARTY ON access_platform_identity(relying_party);

-- changeset auto.generated:1825492372-8
CREATE INDEX FL_PLATFORM_IDENTITY_ROLE_PLATFORM_IDENTITY ON access_platform_identity_role(platform_identity);

-- changeset auto.generated:1825492372-9
CREATE INDEX FL_PLATFORM_IDENTITY_ROLE_ROLE ON access_platform_identity_role(`role`);

-- changeset auto.generated:1825492372-10
CREATE INDEX FL_PLATFORM_IDENTITY_TRUSTED_CLIENT ON access_platform_identity(trusted_client);

-- changeset auto.generated:1825492372-11
CREATE INDEX FL_PLATFORM_IDENTITY_USERS ON access_platform_identity(user);

-- changeset auto.generated:1825492372-12
CREATE UNIQUE INDEX UK_PLATFORM_IDENTITY_ROLE_ROLE_PLATFORM_IDENTITY ON access_platform_identity_role(`role`, platform_identity);

-- changeset auto.generated:1825492372-13
ALTER TABLE access_client_identity ADD CONSTRAINT FK_ACCESS_CLIENT_IDENTITY_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-14
ALTER TABLE access_client_identity ADD CONSTRAINT FK_ACCESS_CLIENT_IDENTITY_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-15
ALTER TABLE access_client_identity ADD CONSTRAINT FK_ACCESS_CLIENT_IDENTITY_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-16
ALTER TABLE access_platform_identity ADD CONSTRAINT FK_ACCESS_PLATFORM_IDENTITY_RELYING_PARTY FOREIGN KEY (relying_party) REFERENCES access_relying_party (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-17
ALTER TABLE access_platform_identity_role ADD CONSTRAINT FK_ACCESS_PLATFORM_IDENTITY_ROLE_PLATFORM_IDENTITY FOREIGN KEY (platform_identity) REFERENCES access_platform_identity (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-18
ALTER TABLE access_platform_identity_role ADD CONSTRAINT FK_ACCESS_PLATFORM_IDENTITY_ROLE_ROLE FOREIGN KEY (`role`) REFERENCES access_role (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-19
ALTER TABLE access_platform_identity ADD CONSTRAINT FK_ACCESS_PLATFORM_IDENTITY_TRUSTED_CLIENT FOREIGN KEY (trusted_client) REFERENCES access_trusted_client (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-20
ALTER TABLE access_platform_identity ADD CONSTRAINT FK_ACCESS_PLATFORM_IDENTITY_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

