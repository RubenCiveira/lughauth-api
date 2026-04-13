# 04-04 — Organizaciones y Grupos Jerárquicos

**Prioridad:** P3  
**Esfuerzo estimado:** Alto (5-7 días)  
**Dependencias previas:** ninguna (independiente)

---

## Contexto

El modelo actual tiene Tenant > User > Role (plano). Para aplicaciones SaaS
multi-empresa es necesario un nivel de organización dentro de un tenant:
Tenant > Organización > Usuario con rol en la organización.

Caso de uso típico: una empresa (tenant) tiene múltiples departamentos
(organizaciones). Los usuarios tienen roles distintos en cada departamento.

---

## Qué implementar

```
CRUD /api/access/organizations
CRUD /api/access/organizations/{org_uid}/members
GET  /api/access/organizations/{org_uid}/members/{user_uid}
GET  /api/me/organizations          # Organizaciones del usuario autenticado
```

**Claim en JWT:**
Si el scope `organizations` está concedido y el usuario pertenece a una o más
organizaciones, el access token incluye:
```json
{
  "orgs": [
    { "id": "uuid-org", "name": "Ingeniería", "roles": ["admin"] }
  ]
}
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_organization (
  uid         VARCHAR(36)   NOT NULL,
  tenant_id   VARCHAR(36)   NOT NULL,
  name        VARCHAR(200)  NOT NULL,
  slug        VARCHAR(100)  NOT NULL,           -- identificador URL-friendly
  parent_uid  VARCHAR(36)   NULL,               -- para jerarquía
  metadata_json TEXT        NULL,
  enabled     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_org_slug_tenant (tenant_id, slug),
  INDEX idx_org_parent (parent_uid),
  INDEX idx_org_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_organization_member (
  uid          VARCHAR(36)  NOT NULL,
  tenant_id    VARCHAR(36)  NOT NULL,
  org_uid      VARCHAR(36)  NOT NULL,
  user_uid     VARCHAR(36)  NOT NULL,
  role_uid     VARCHAR(36)  NULL,               -- rol en esta organización
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  UNIQUE KEY uk_org_member (org_uid, user_uid),
  INDEX idx_org_member_user (user_uid, tenant_id),
  INDEX idx_org_member_org  (org_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/Organization/

Estructura hexagonal completa (igual que `TrustedClient`):

```
src/Features/Access/Organization/
├── Domain/
│   ├── Organization.php
│   ├── OrganizationMember.php
│   ├── ValueObject/
│   │   ├── OrganizationNameVO.php
│   │   ├── OrganizationSlugVO.php
│   │   └── OrganizationParentVO.php
│   └── Gateway/
│       ├── OrganizationReadGateway.php
│       └── OrganizationWriteGateway.php
├── Application/
│   ├── Policy/Allow/{Create,Update,Delete,List,Retrieve}/...
│   └── Usecase/{Create,Update,Delete,List,Retrieve,AddMember,RemoveMember}/...
└── Infrastructure/...
```

### C. Domain — Organization

```php
final class Organization
{
    public function __construct(
        public readonly string $uid,
        public readonly string $tenant,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $parentUid,
        public readonly bool $enabled,
        public readonly \DateTimeImmutable $createdAt,
    ) {}
}
```

### D. OrganizationReadGateway

```php
interface OrganizationReadGateway
{
    public function findById(string $uid, string $tenant): ?Organization;
    /** @return Organization[] */
    public function findByTenant(string $tenant): array;
    /** @return Organization[] sub-organizaciones directas */
    public function findChildren(string $parentUid, string $tenant): array;
    /** @return OrganizationMember[] */
    public function findMembersByOrg(string $orgUid, string $tenant): array;
    /** @return Organization[] organizaciones del usuario */
    public function findByUser(string $userUid, string $tenant): array;
}
```

### E. Token Granter — claim orgs

**Archivo:** donde se construye el payload del access token

Si el scope `organizations` está en el token:
```php
if (in_array('organizations', $scopes, true)) {
    $orgs = $this->orgReadGateway->findByUser($userUid, $tenant);
    if (!empty($orgs)) {
        $payload['orgs'] = array_map(fn($o) => [
            'id'    => $o->uid,
            'name'  => $o->name,
            'roles' => [$this->getMemberRole($userUid, $o->uid, $tenant)],
        ], $orgs);
    }
}
```

### F. Herencia de permisos

Opcional (P3+): Si una organización hereda permisos del padre, un usuario
en la organización hija puede tener los permisos de la organización padre.

Implementar como un método de traversal:
```php
public function getEffectiveRoles(string $userUid, string $orgUid, string $tenant): array
{
    $directRoles = $this->getMemberRole($userUid, $orgUid, $tenant);
    $parent = $this->findParent($orgUid, $tenant);
    if ($parent !== null) {
        $parentRoles = $this->getEffectiveRoles($userUid, $parent->uid, $tenant);
        return array_unique(array_merge($directRoles, $parentRoles));
    }
    return $directRoles;
}
```

---

## Tests a incluir

### Test unitario — Organization (dominio)

- Slug generado automáticamente desde nombre si no se proporciona
- Validación: slug solo letras, números y guiones
- `parentUid` no puede ser el mismo `uid` (auto-referencia)

### Test unitario — GetEffectiveRoles (herencia)

Con mock de gateway:
- Usuario en org hija → hereda roles de org padre
- Ciclo en jerarquía (bug) → no loop infinito (máximo depth 10)
- Sin padre → solo roles directos

### Test integración — Organization CRUD

- `POST /api/access/organizations` admin → `201`
- `POST /api/access/organizations` usuario normal → `403`
- `GET /api/access/organizations/{uid}` → `200` con hijos
- `POST /api/access/organizations/{uid}/members` → añadir usuario
- `DELETE /api/access/organizations/{uid}/members/{user_uid}` → quitar miembro
- `GET /api/me/organizations` → organizaciones del usuario autenticado
- Access token con scope `organizations` → claim `orgs` presente en JWT
