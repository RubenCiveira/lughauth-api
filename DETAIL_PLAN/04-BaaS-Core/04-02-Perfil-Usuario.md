# 04-02 — Perfil de Usuario Extendido (Claims OIDC)

**Prioridad:** P1  
**Spec:** OIDC Core 1.0 — Standard Claims §5.1  
**Esfuerzo estimado:** Bajo-Medio (2-3 días)  
**Dependencias previas:** ninguna

---

## Contexto

El usuario actual en `access_user` solo tiene `email`, `password`, `mfa_seed`,
`temporal_password`, `enabled`. El estándar OIDC define claims de perfil que el
`/userinfo` endpoint debe devolver cuando se concede el scope `profile`.

Sin estos campos, las aplicaciones deben mantener su propio perfil de usuario,
duplicando datos y creando inconsistencias.

Vamos a hacerlo siguiendo la filosofia para los contextos de oidc: la implementación
operativa se mantiene dentro de oidc, y sólamente tendremos un adaptador que usará
los gateways o casos de uso de los contextos de access para la persistencia de los datos. Access/Profile será el contexto maestro para registrar los datos. No se deberán hacer cambios en los contextos de Access, sólo en los de Oidc.

Para ello necesitaremos un entrypoint para pintar la vista del perfil del usuario en
html.

Vamos a mover la clase DecorateHtml y los temas a un nuevo context Oidc/Theme para que
tanto desde Authentication como desde el nuevo contexto Profile se puedan usar la misma
lógica de personalización de la interfaz de usaurio.

Crearemos un nuevo contexto en Oidc llamado Profile que expondrá un endpoint en 
infraestructure/Driver/Html con la página mostrando el perfil del usaurio y un botón
para ir a edición del perfile.

Esa misma página de perfil también servirá como punto de entrada de la auto gestión del
usuario (donde más adelante tendrémos el boton de cambio de contraseña, visualizado de
las sessiones activas, los modos de mfa, etc...) con lo que usemos un enfoque similar
al de Authentication de un contenedor y paneles.

---

## Qué implementar

### Claims OIDC estándar a añadir

| Claim | Tipo | Scope |
|-------|------|-------|
| `name` | string | `profile` |
| `given_name` | string | `profile` |
| `family_name` | string | `profile` |
| `middle_name` | string | `profile` |
| `nickname` | string | `profile` |
| `preferred_username` | string | `profile` |
| `picture` | string (URL) | `profile` |
| `website` | string (URL) | `profile` |
| `gender` | string | `profile` |
| `birthdate` | string (YYYY-MM-DD) | `profile` |
| `zoneinfo` | string (IANA tz) | `profile` |
| `locale` | string (BCP 47) | `profile` |
| `phone_number` | string | `phone` |
| `phone_number_verified` | bool | `phone` |
| `address` | object | `address` |
| `updated_at` | timestamp | `profile` |

### API de gestión de perfil

```
GET /api/me/profile              # Leer perfil propio
PUT /api/me/profile              # Actualizar perfil propio
GET /api/access/users/{uid}/profile   # Admin: leer perfil de cualquier usuario
PUT /api/access/users/{uid}/profile   # Admin: actualizar perfil de cualquier usuario
```

---

## Dónde y cómo hacer los cambios

### A. Migración — tabla access_user_profile

En lugar de añadir columnas a `access_user` (que es una entidad grande), crear
una tabla separada con relación 1:1:

```sql
CREATE TABLE IF NOT EXISTS access_user_profile (
  uid                  VARCHAR(36)   NOT NULL,
  tenant_id            VARCHAR(36)   NOT NULL,
  user_uid             VARCHAR(36)   NOT NULL UNIQUE,
  given_name           VARCHAR(200)  NULL,
  family_name          VARCHAR(200)  NULL,
  middle_name          VARCHAR(200)  NULL,
  nickname             VARCHAR(200)  NULL,
  preferred_username   VARCHAR(200)  NULL,
  picture_url          VARCHAR(500)  NULL,
  website_url          VARCHAR(500)  NULL,
  gender               VARCHAR(50)   NULL,
  birthdate            DATE          NULL,
  zoneinfo             VARCHAR(100)  NULL,
  locale               VARCHAR(20)   NULL,
  phone_number         VARCHAR(30)   NULL,
  phone_number_verified TINYINT(1)   NOT NULL DEFAULT 0,
  address_json         TEXT          NULL,   -- JSON object (OIDC address claim)
  updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_profile_user (user_uid),
  INDEX idx_profile_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/UserProfile/

```
src/Features/Access/UserProfile/
├── Domain/
│   ├── UserProfile.php
│   ├── ValueObject/
│   │   ├── UserProfileGivenNameVO.php
│   │   ├── UserProfileFamilyNameVO.php
│   │   ├── UserProfileLocaleVO.php
│   │   └── ... (un VO por campo)
│   └── Gateway/
│       ├── UserProfileReadGateway.php
│       └── UserProfileWriteGateway.php
├── Application/
│   └── Usecase/
│       ├── GetUserProfile/
│       │   └── GetUserProfileUsecase.php
│       └── UpdateUserProfile/
│           ├── UpdateUserProfileUsecase.php
│           └── UpdateUserProfileParams.php
└── Infrastructure/
    ├── Driven/
    │   ├── UserProfileReadAdapter.php
    │   └── UserProfileWriteAdapter.php
    └── Driver/
        └── Rest/
            ├── UserProfileController.php    # GET/PUT /api/me/profile
            └── UserProfileApiDTO.php
```

### C. Domain — UserProfile

```php
final class UserProfile
{
    public function __construct(
        public readonly string $uid,
        public readonly string $userUid,
        public readonly string $tenant,
        public readonly ?string $givenName,
        public readonly ?string $familyName,
        public readonly ?string $middleName,
        public readonly ?string $nickname,
        public readonly ?string $preferredUsername,
        public readonly ?string $pictureUrl,
        public readonly ?string $websiteUrl,
        public readonly ?string $gender,
        public readonly ?\DateTimeImmutable $birthdate,
        public readonly ?string $zoneinfo,
        public readonly ?string $locale,
        public readonly ?string $phoneNumber,
        public readonly bool $phoneNumberVerified,
        public readonly ?array $address,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    public function toOidcClaims(): array
    {
        return array_filter([
            'name'                  => trim(($this->givenName ?? '') . ' ' . ($this->familyName ?? '')) ?: null,
            'given_name'            => $this->givenName,
            'family_name'           => $this->familyName,
            'middle_name'           => $this->middleName,
            'nickname'              => $this->nickname,
            'preferred_username'    => $this->preferredUsername,
            'picture'               => $this->pictureUrl,
            'website'               => $this->websiteUrl,
            'gender'                => $this->gender,
            'birthdate'             => $this->birthdate?->format('Y-m-d'),
            'zoneinfo'              => $this->zoneinfo,
            'locale'                => $this->locale,
            'phone_number'          => $this->phoneNumber,
            'phone_number_verified' => $this->phoneNumber ? $this->phoneNumberVerified : null,
            'address'               => $this->address,
            'updated_at'            => $this->updatedAt->getTimestamp(),
        ], fn($v) => $v !== null);
    }
}
```

### D. UserInfo endpoint — incluir claims de perfil

**Archivo:** `src/Features/Oidc/Authentication/Infrastructure/Driver/Rest/UserInfoController.php`

```php
public function get(ServerRequestInterface $request, ...): ResponseInterface
{
    $userUid = $this->extractSubFromToken($request);
    $scopes  = $this->extractScopesFromToken($request);

    $claims = ['sub' => $userUid];

    // scope: email
    if (in_array('email', $scopes, true)) {
        $user = $this->userLoader->load($userUid, $tenant);
        $claims['email']          = $user->email;
        $claims['email_verified'] = true;
    }

    // scope: profile
    if (in_array('profile', $scopes, true)) {
        $profile = $this->profileGateway->findByUser($userUid, $tenant);
        if ($profile) {
            $claims = array_merge($claims, $profile->toOidcClaims());
        }
    }

    // scope: phone
    if (in_array('phone', $scopes, true)) {
        $profile ??= $this->profileGateway->findByUser($userUid, $tenant);
        $claims['phone_number']          = $profile?->phoneNumber;
        $claims['phone_number_verified'] = $profile?->phoneNumberVerified;
    }

    return $this->json($response, $claims);
}
```

### E. id_token — incluir claims de perfil

**Archivo:** donde se construye el payload del `id_token`
(probablemente en `JoseTokenSigner.php` o en `TokenGranterMediator.php`)

Si el scope incluye `profile`, añadir `name`, `given_name`, `family_name`, `picture`
al payload del id_token (no solo en userinfo).

---

## Tests a incluir

### Test unitario — UserProfile.toOidcClaims()

- Perfil completo → todos los claims presentes
- Perfil con campos null → no aparecen en el resultado
- `name` = `given_name + ' ' + family_name` (sin espacios extra)
- `updated_at` como timestamp Unix
- `phone_number_verified` solo presente si `phone_number` tiene valor

### Test unitario — UpdateUserProfileUsecase

Con mocks:
- Actualización válida → gateway llamado con datos correctos
- `locale` inválido (no BCP 47) → excepción de validación
- `birthdate` en futuro → excepción de validación
- Usuario no existente → excepción Not Found

### Test integración — /api/me/profile

- `GET /api/me/profile` sin Bearer → `401`
- `GET /api/me/profile` sin perfil creado → `200` con objeto vacío
- `PUT /api/me/profile` → `200` con datos actualizados
- `GET /userinfo` con scope `profile` → incluye `given_name`, `family_name`, `picture`
- `GET /userinfo` con scope `email` → no incluye claims de perfil
