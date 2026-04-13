-- liquibase formatted sql

-- changeset pkce:20260413120000-1
ALTER TABLE _oauth_temporal_codes
  ADD COLUMN code_challenge VARCHAR(128) NULL,
  ADD COLUMN code_challenge_method VARCHAR(10) NULL;
