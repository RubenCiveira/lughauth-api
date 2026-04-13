# 05-01 — Sistema de Webhooks

**Prioridad:** P2  
**Esfuerzo estimado:** Medio-Alto (4-5 días)  
**Dependencias previas:** Symfony EventDispatcher (ya integrado)

---

## Contexto

Los webhooks permiten notificar a aplicaciones externas cuando ocurren eventos
de autenticación. Actualmente LughAuth usa `_output_queue_pending_events` para
eventos internos pero no tiene forma de notificar a sistemas externos.

---

## Qué implementar

### Eventos publicables

```
user.created          user.updated         user.deleted
user.login.success    user.login.failed    user.logout
user.password.changed user.mfa.enabled     user.mfa.disabled
token.issued          token.revoked
session.created       session.revoked
```

### API de gestión

```
GET/POST   /api/access/webhooks
GET/PUT/DELETE /api/access/webhooks/{uid}
GET        /api/access/webhooks/{uid}/deliveries
POST       /api/access/webhooks/{uid}/test
```

---

## Dónde y cómo hacer los cambios

### A. Migración

```sql
CREATE TABLE IF NOT EXISTS access_webhook_endpoint (
  uid          VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  url          VARCHAR(500)  NOT NULL,
  secret       TEXT          NOT NULL,       -- para firmar el payload (encriptado en DB)
  events_json  TEXT          NOT NULL,       -- JSON array de eventos suscritos
  enabled      TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  description  VARCHAR(200)  NULL,
  PRIMARY KEY (uid),
  INDEX idx_webhook_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_webhook_delivery (
  uid          VARCHAR(36)   NOT NULL,
  webhook_uid  VARCHAR(36)   NOT NULL,
  tenant_id    VARCHAR(36)   NOT NULL,
  event_type   VARCHAR(100)  NOT NULL,
  payload_json TEXT          NOT NULL,
  response_status INT        NULL,
  response_body   TEXT       NULL,
  duration_ms     INT        NULL,
  attempt         TINYINT    NOT NULL DEFAULT 1,
  next_retry_at   DATETIME   NULL,
  delivered_at    DATETIME   NULL,
  created_at      DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (uid),
  INDEX idx_delivery_webhook (webhook_uid),
  INDEX idx_delivery_retry   (next_retry_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B. Nuevo sub-feature: src/Features/Access/Webhook/

```
src/Features/Access/Webhook/
├── Domain/
│   ├── WebhookEndpoint.php
│   ├── WebhookDelivery.php
│   ├── WebhookEvent.php                    # VO del evento a publicar
│   └── Gateway/
│       ├── WebhookEndpointGateway.php
│       └── WebhookDeliveryGateway.php
├── Application/
│   ├── Service/
│   │   └── WebhookDispatcher.php           # Servicio principal de dispatching
│   └── Usecase/
│       ├── CreateWebhook/...
│       ├── ListWebhooks/...
│       ├── DeleteWebhook/...
│       └── TestWebhook/...
└── Infrastructure/
    ├── Driven/
    │   ├── WebhookEndpointSqlAdapter.php
    │   └── WebhookDeliverySqlAdapter.php
    └── Driver/
        └── Rest/
            └── WebhookController.php
```

### C. Domain — WebhookEvent

```php
final class WebhookEvent
{
    public function __construct(
        public readonly string $id,           // UUID único del evento
        public readonly string $type,         // 'user.login.success'
        public readonly string $tenant,
        public readonly \DateTimeImmutable $createdAt,
        public readonly array  $data,         // payload específico del evento
    ) {}

    public function toPayload(): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'tenant'     => $this->tenant,
            'created_at' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'data'       => $this->data,
        ];
    }
}
```

### D. Application — WebhookDispatcher

```php
final class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookEndpointGateway $endpoints,
        private readonly WebhookDeliveryGateway $deliveries,
        private readonly HttpClientInterface $http,
    ) {}

    public function dispatch(WebhookEvent $event): void
    {
        // 1. Encontrar endpoints suscritos al tipo de evento
        $endpoints = $this->endpoints->findSubscribedTo($event->type, $event->tenant);

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint, $event);
        }
    }

    private function deliver(WebhookEndpoint $endpoint, WebhookEvent $event): void
    {
        $payload   = json_encode($event->toPayload());
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $endpoint->secret);

        $delivery = WebhookDelivery::pending($endpoint->uid, $event);
        $this->deliveries->store($delivery);

        try {
            $start    = microtime(true);
            $response = $this->http->post($endpoint->url, [
                'body'    => $payload,
                'headers' => [
                    'Content-Type'            => 'application/json',
                    'X-LughAuth-Event'        => $event->type,
                    'X-LughAuth-Delivery'     => $delivery->uid,
                    'X-LughAuth-Signature'    => $signature,
                    'User-Agent'              => 'LughAuth-Webhooks/1.0',
                ],
                'timeout' => 10,
            ]);
            $duration = (int)((microtime(true) - $start) * 1000);

            $this->deliveries->markDelivered(
                $delivery->uid,
                $response->getStatusCode(),
                substr((string) $response->getBody(), 0, 500),
                $duration,
            );
        } catch (\Throwable $e) {
            $this->deliveries->markFailed($delivery->uid, $e->getMessage(), $this->nextRetryAt($delivery->attempt));
        }
    }

    private function nextRetryAt(int $attempt): \DateTimeImmutable
    {
        // Exponential backoff: 1m, 5m, 30m, 2h, 10h
        $minutes = [1, 5, 30, 120, 600][$attempt - 1] ?? 600;
        return new \DateTimeImmutable("+{$minutes} minutes");
    }
}
```

### E. Integración con EventDispatcher de Symfony

En los Listeners existentes (ej. `NotifyLogin.php` en `Oidc/User/Application/Listener/`),
añadir dispatch del webhook:

```php
class NotifyLogin implements EventSubscriberInterface
{
    public function onLoginSuccess(UserLoginEvent $event): void
    {
        // Código existente de notificación...

        // Nuevo: dispatch webhook
        $this->webhookDispatcher->dispatch(new WebhookEvent(
            id:        Uuid::uuid4()->toString(),
            type:      'user.login.success',
            tenant:    $event->tenant,
            createdAt: new \DateTimeImmutable(),
            data:      [
                'user_uid'    => $event->userUid,
                'ip_address'  => $event->ipAddress,
                'client_id'   => $event->clientId,
            ],
        ));
    }
}
```

**Alternativa más limpia:** crear un `WebhookEventListener` genérico que escuche
todos los eventos de dominio y los mapee a `WebhookEvent`.

### F. Retry Job

**Archivo:** `src/Features/Access/Webhook/Infrastructure/Driver/Cli/RetryWebhookDeliveriesCommand.php`

```php
// Buscar deliveries fallidas con next_retry_at <= NOW()
// Reintentar con WebhookDispatcher::deliver()
// Máximo 5 intentos, después marcar como permanently_failed
```

Ejecutar cada 1 minuto via cron o scheduler.

### G. Endpoint de test

```php
// POST /api/access/webhooks/{uid}/test
// Envía un evento de prueba: { id: uuid, type: 'webhook.test', data: { message: 'Hello!' } }
// Responde con el resultado de la entrega inmediata
```

---

## Tests a incluir

### Test unitario — WebhookDispatcher

Con mocks de `WebhookEndpointGateway`, `WebhookDeliveryGateway`, `HttpClientInterface`:

- 2 endpoints suscritos al tipo → 2 llamadas HTTP
- Endpoint no suscrito al tipo → no se llama
- HTTP responde `200` → `markDelivered()` llamado
- HTTP falla → `markFailed()` llamado con `nextRetryAt` correcto
- Payload firmado: header `X-LughAuth-Signature: sha256=<hmac>`
- Backoff exponencial: 1m, 5m, 30m... según número de intento

### Test unitario — Firma del payload

- `sha256 = hash_hmac('sha256', payload, secret)` → verificable
- Firma distinta si secret diferente

### Test integración — WebhookController

- `POST /api/access/webhooks` → `201`
- `GET /api/access/webhooks/{uid}/deliveries` → lista de intentos
- `POST /api/access/webhooks/{uid}/test` → delivery creado, respuesta con status
- Login del usuario → delivery registrado para webhooks suscritos a `user.login.success`
