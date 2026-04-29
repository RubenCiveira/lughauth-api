# 05-02 — Event Streaming (SSE)

**Prioridad:** P4  
**Esfuerzo estimado:** Alto (4-5 días)  
**Dependencias previas:** 05-01 Webhooks (o AMQP existente)

---

## Contexto

Server-Sent Events permite que el servidor envíe eventos al browser o a un cliente
HTTP sin polling. Útil para dashboards de administración en tiempo real.

---

## Qué implementar

```
GET /api/stream/events?topics=user.login,session.created
Authorization: Bearer <token con scope stream:events>
```

Respuesta (text/event-stream):
```
data: {"id":"uuid","type":"user.login.success","data":{"user_uid":"..."}}

data: {"id":"uuid","type":"session.created","data":{...}}
```

---

## Dónde y cómo hacer los cambios

### A. SSE Controller

**Archivo nuevo:** `src/Features/Access/Webhook/Infrastructure/Driver/Rest/EventStreamController.php`

```php
public function stream(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    // 1. Verificar autenticación y scope stream:events
    $topics = explode(',', $request->getQueryParams()['topics'] ?? '*');

    // 2. Configurar headers SSE
    $response = $response
        ->withHeader('Content-Type', 'text/event-stream')
        ->withHeader('Cache-Control', 'no-cache')
        ->withHeader('X-Accel-Buffering', 'no');  // para Nginx

    // 3. Suscribirse a _output_queue_pending_events o AMQP
    // 4. Escribir eventos como stream (connection abierta max 30s, luego reconnect)
}
```

### B. Backend del stream

Dos opciones según infraestructura:

**Opción A — Polling de DB** (simple, para tráfico bajo):
- Cada 500ms hacer `SELECT * FROM _output_queue_pending_events WHERE tenant_id=? AND created_at > ?`
- `flush()` cada evento nuevo

**Opción B — AMQP** (robusto, para producción):
- Usar el `enqueue/amqp-lib` existente
- Crear una queue temporal por conexión SSE
- Bind a los topics solicitados
- Consumir y hacer forward al stream HTTP

---

## Tests a incluir

### Test unitario — EventStreamController

- Verify que respuesta tiene `Content-Type: text/event-stream`
- Sin Bearer → `401`
- Sin scope `stream:events` → `403`
- Evento en DB → aparece en stream
