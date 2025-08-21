--liquibase formatted sql

--changeset auto.generated:1825492372-2
CREATE TABLE _outbox_events (event_id CHAR(36) NOT NULL, status ENUM('pending', 'published', 'failed') DEFAULT 'pending' NOT NULL, next_retry datetime DEFAULT null NULL, retries INT UNSIGNED NOT NULL, event_type VARCHAR(120) NOT NULL, schema_version VARCHAR(16) NOT NULL, occurred_at datetime NOT NULL, payload LONGTEXT NOT NULL, diff LONGTEXT NULL, correlation_id CHAR(36) NULL, causation_id CHAR(36) NULL, created_at timestamp NOT NULL, published_at datetime DEFAULT null NULL, CONSTRAINT PK__OUTBOX_EVENTS PRIMARY KEY (event_id));

--changeset auto.generated:1825492372-3
CREATE INDEX idx_published_at ON _outbox_events(published_at);

--changeset auto.generated:1825492372-4
CREATE INDEX idx_status_next_retry ON _outbox_events(status, next_retry);

