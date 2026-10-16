# OIDC Module Documentation

Analysis documents for the `Features/Oidc` bounded context.

These documents describe the domain model, application flows, and gateway contracts. They are intentionally decoupled from any specific infrastructure implementation — the domain and application layers can be migrated to a different technology stack by re-implementing the gateway interfaces described in [02-gateway-contracts.md](02-gateway-contracts.md).

## Documents

| Document | Contents |
|----------|---------|
| [00-overview.md](00-overview.md) | Module purpose, architecture, submodule summary, endpoint list, port interface index |
| [01-domain-model.md](01-domain-model.md) | All domain entities, value objects, enumerations, and domain exceptions |
| [02-gateway-contracts.md](02-gateway-contracts.md) | Port interface contracts — what each gateway must do (implementation-agnostic) |
| [03-authorization-code-flow.md](03-authorization-code-flow.md) | Browser-based Authorization Code Flow, authentication step state machine |
| [04-token-grants.md](04-token-grants.md) | Token endpoint: all grant types, signing details, introspection, userinfo |
| [05-device-and-par-flows.md](05-device-and-par-flows.md) | Device Authorization Grant (RFC 8628) and Pushed Authorization Requests (RFC 9126) |
| [06-security-model.md](06-security-model.md) | PKCE, CSID, client-side encryption, token signing, key rotation, request objects |
| [07-federated-login.md](07-federated-login.md) | Federated / social login architecture, provider contract, provisioning flow |
| [08-session-and-key-management.md](08-session-and-key-management.md) | Session lifecycle, symmetric key rotation, authorization codes, RSA key management |

## What is NOT covered here

- Concrete adapter implementations (SQL schema, specific user directory structures).
- Infrastructure-specific configuration (database connections, environment variables).
- Deployment topology.

For the current infrastructure adapters, see `src/Features/Oidc/*/Infrastructure/Driven/`.
