# Oidc Bounded Context

Implements the OpenID Connect Discovery document (`/.well-known/openid-configuration`) and Mutual TLS endpoint aliases.

## Responsibility

This context owns the **OIDC metadata publication layer**: constructing and serving the discovery document that clients use to auto-configure themselves. It aggregates static server capabilities with dynamic per-tenant endpoints and supports extensibility via a contributor gateway pattern.

## Domain Concepts

| Concept | Type | Description |
|---|---|---|
| `OpenIdConfiguration` | ValueObject | The complete OIDC discovery document: issuer, all endpoint URIs, supported response types, grant types, scopes, signing algorithms, claim types, PKCE methods, and feature support flags |
| `MtlsEndpointAliases` | ValueObject | Mutual TLS endpoint aliases as defined in RFC 8705 (e.g., mTLS-specific token endpoint) |

## Ports (Gateways)

| Gateway | Responsibility |
|---|---|
| `DiscoveryContributorGateway` | Plugin interface allowing other contexts to add fields to the discovery document (e.g., the Mutual TLS contributor adds `mtls_endpoint_aliases`) |

## Infrastructure

### Driven (Outbound Adapters)

- **`MtlsDiscoveryContributor`** — Implements `DiscoveryContributorGateway`; appends `mtls_endpoint_aliases` to the discovery document.

### Driver (Inbound Adapters)

- **`Rest/OpenIdConfigurationController`** — `GET /.well-known/openid-configuration` — returns the discovery document as JSON.

## Key Invariants

- The discovery document is **tenant-scoped**: each tenant exposes its own issuer and endpoint URLs.
- Contributors are applied in registration order; the base `OpenIdConfiguration` is built first, then each contributor enriches it.
- The document is **cached** at the HTTP layer — changes to tenant configuration (new provider, new scope) must invalidate the cache.

## Interactions with Other Contexts

```
Oidc ──references endpoints from──> Authentication   (authorize, token, userinfo, logout)
     ──references endpoints from──> TokenSecurity    (JWKS URI)
     ──references endpoints from──> Par              (pushed_authorization_request_endpoint)
     ──references endpoints from──> Device           (device_authorization_endpoint)
     ──extended by──>               MtlsDiscoveryContributor (mtls_endpoint_aliases)
```
