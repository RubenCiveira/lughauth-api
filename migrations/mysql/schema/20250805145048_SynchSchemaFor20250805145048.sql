--liquibase formatted sql

--changeset auto.generated:1825492372-1
CREATE TABLE _audit_changelog (entity_type VARCHAR(255) NOT NULL, entity_id VARCHAR(255) NOT NULL, deleted BIT(1) DEFAULT 0 NOT NULL, changed_at timestamp NOT NULL, payload LONGTEXT NULL, CONSTRAINT PK__AUDIT_CHANGELOG PRIMARY KEY (entity_type, entity_id));

--changeset auto.generated:1825492372-2
CREATE TABLE _audit_sync_cursor (client_id VARCHAR(255) NOT NULL, entity_type VARCHAR(255) NOT NULL, last_changed_at timestamp NOT NULL, last_entity_id CHAR(36) NOT NULL, CONSTRAINT PK__AUDIT_SYNC_CURSOR PRIMARY KEY (client_id, entity_type));

--changeset auto.generated:1825492372-3
CREATE TABLE _sync_entities (name VARCHAR(255) NOT NULL, last_start datetime NOT NULL, last_call datetime NOT NULL, cursor_position LONGTEXT NULL);

--changeset auto.generated:1825492372-4
CREATE INDEX idx_audit_changelog_ordering ON _audit_changelog(changed_at, entity_id);

--changeset auto.generated:1825492372-5
CREATE INDEX idx_audit_changelog_type ON _audit_changelog(entity_type);

