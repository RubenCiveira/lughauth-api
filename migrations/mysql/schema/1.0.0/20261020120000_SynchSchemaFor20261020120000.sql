-- Signing keys are looked up by tenant and validity window (B.3, tasks 029/050).
CREATE INDEX IX__OAUTH_KEYS_STORER_TENANT_WINDOW ON _oauth_keys_storer(tenant, since, expiration);
