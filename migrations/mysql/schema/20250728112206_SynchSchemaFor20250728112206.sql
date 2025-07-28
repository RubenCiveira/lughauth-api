--liquibase formatted sql

--changeset auto.generated:1825492372-1
CREATE TABLE _audit_action (id CHAR(36) NOT NULL, occurred_at datetime NOT NULL, actor_id VARCHAR(255) NOT NULL, actor_type VARCHAR(50) NOT NULL, actor_ip VARCHAR(45) NULL, tenant_id VARCHAR(255) NULL, session_id VARCHAR(255) NULL, client_id VARCHAR(255) NULL, user_agent TEXT NULL, request_method VARCHAR(10) NOT NULL, request_path TEXT NOT NULL, action_type VARCHAR(255) NOT NULL, metadata LONGTEXT NULL, CONSTRAINT PK__AUDIT_ACTION PRIMARY KEY (id));

--changeset auto.generated:1825492372-2
CREATE TABLE _audit_change (id CHAR(36) NOT NULL, action_id CHAR(36) NOT NULL, target_type VARCHAR(100) NOT NULL, target_id VARCHAR(255) NOT NULL, change_type VARCHAR(20) DEFAULT 'update' NOT NULL, change_order SMALLINT DEFAULT 0 NOT NULL, payload LONGTEXT NOT NULL, metadata LONGTEXT NULL, CONSTRAINT PK__AUDIT_CHANGE PRIMARY KEY (id));

--changeset auto.generated:1825492372-3
ALTER TABLE access_relying_party ADD policies LONGTEXT NULL;

--changeset auto.generated:1825492372-4
ALTER TABLE access_relying_party ADD `schemas` LONGTEXT NOT NULL;

--changeset auto.generated:1825492372-5
ALTER TABLE access_relying_party ADD scopes LONGTEXT NOT NULL;

--changeset auto.generated:1825492372-6
CREATE INDEX fk_audit_change_action ON _audit_change(action_id);

--changeset auto.generated:1825492372-7
CREATE INDEX idx_audit_action_actor ON _audit_action(actor_id);

--changeset auto.generated:1825492372-8
CREATE INDEX idx_audit_action_occurred_at ON _audit_action(occurred_at);

--changeset auto.generated:1825492372-9
CREATE INDEX idx_audit_change_target ON _audit_change(target_type, target_id);

--changeset auto.generated:1825492372-10
ALTER TABLE _audit_change ADD CONSTRAINT fk_audit_change_action FOREIGN KEY (action_id) REFERENCES _audit_action (id) ON UPDATE RESTRICT ON DELETE CASCADE;

