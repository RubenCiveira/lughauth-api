# Common Bounded Context

Cross-cutting infrastructure shared across all OAuth bounded contexts: template installation, database migrations, email delivery, and user loading.

## Responsibility

This context holds **shared infrastructure** that does not belong to any single bounded context. It provides:
- A full **HTML template installation system** for the authorization UI.
- The **database migration provider** for all OAuth-related schema migrations.
- A shared **email adapter** used by contexts that send transactional email (MagicLink, UserInvitation, User).
- A **user loader adapter** that resolves user entities for contexts that need to hydrate a user from an identifier.

## Template Installation (`Application/Usecase/Install/`)

Each `Install*` use case writes a specific UI template to the tenant's template store. Templates are standalone HTML files rendered by the `Theme` context.

| Use Case | Template |
|---|---|
| `InstallLoginTemplate` | Standard login form |
| `InstallMagicLinkTemplate` (Send) | Magic link request form |
| `InstallFullPageTemplate` | Full-page layout wrapper |
| `InstallIndexPageTemplate` | Authorization landing page |
| `InstallInvitationTemplate` | Invitation acceptance page |
| `InstallPasswordRecoverTemplate` | Password recovery form |
| `InstallUserRegisterTemplate` | User registration form |
| `InstallDelegateLoginTemplate` | Social/federated login buttons |
| `InstallCorporateTheme` | Corporate theme assets |
| `InstallAskForDeleteTemplate` | Account deletion confirmation |
| `InstallTemplateHtml` | Base HTML template with asset injection |
| `InstallUsecase` | Orchestrates all template installs in sequence |

## Infrastructure

### Driven (Outbound Adapters)

- **`OAuthMailAdapter`** — Concrete mail delivery adapter shared by MagicLink, UserInvitation, and User notification flows.
- **`UserLoaderAdapter`** — Resolves a full user record from a user identifier; used by contexts that need user attributes without owning the user domain.

### Driver (Inbound Adapters)

- **`Management/OAuthMigrationProvider`** — Registers all OAuth database migrations with the migration runner.

## Key Invariants

- `OAuthMailAdapter` is a **shared adapter** — it should not contain business logic; it only handles delivery concerns (SMTP, template rendering, localization).
- Template installation is **idempotent**: running `InstallUsecase` again should not corrupt existing customised templates (it checks before overwriting or uses merge semantics).
- Database migrations registered via `OAuthMigrationProvider` must remain **backwards-compatible** when applied to a running system.

## Interactions with Other Contexts

```
Common ──provides mail to──>    MagicLink       (MagicLinkMailAdapter delegates to OAuthMailAdapter)
       ──provides mail to──>    UserInvitation  (UserInvitationMailAdapter)
       ──provides mail to──>    User            (notification listeners)
       ──provides migrations to──> (all OAuth contexts — schema managed centrally)
       ──provides user loading to──> (any context needing to hydrate a user by id)
```
