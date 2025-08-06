--liquibase formatted sql

--changeset auto.generated:1825492372-1
ALTER TABLE _audit_sync_cursor MODIFY last_entity_id char(36);

