-- liquibase formatted sql

-- changeset auto.generated:1825492372-1
CREATE TABLE access_user_profile (uid VARCHAR(255) NOT NULL, version INT NOT NULL, address_json LONGTEXT NULL, birthdate VARCHAR(255) NULL, family_name VARCHAR(255) NULL, gender VARCHAR(255) NULL, given_name VARCHAR(255) NULL, locale VARCHAR(255) NULL, middle_name VARCHAR(255) NULL, nickname VARCHAR(255) NULL, phone_number VARCHAR(255) NULL, phone_number_verified BIT DEFAULT 0 NULL, picture_url VARCHAR(255) NULL, preferred_username VARCHAR(255) NULL, updated_at VARCHAR(255) NULL, website_url VARCHAR(255) NULL, zoneinfo VARCHAR(255) NULL, user VARCHAR(255) NOT NULL, CONSTRAINT PK_ACCESS_USER_PROFILE PRIMARY KEY (uid), UNIQUE (user));

-- changeset auto.generated:1825492372-2
ALTER TABLE access_user_profile ADD CONSTRAINT FK_ACCESS_USER_PROFILE_USER FOREIGN KEY (user) REFERENCES access_user (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

