# 03 — Autenticación Avanzada (P2/P3)

## Objetivo

Extender las capacidades de autenticación más allá de contraseña + TOTP,
añadiendo métodos modernos y seguros que mejoran la experiencia del usuario
y reducen el riesgo de ataques de phishing.

## Tareas

| Archivo | Descripción | Prioridad |
|---------|-------------|-----------|
| [03-01-WebAuthn-Passkeys.md](03-01-WebAuthn-Passkeys.md) | FIDO2 / WebAuthn / Passkeys | P2 |
| [03-02-Magic-Links.md](03-02-Magic-Links.md) | Autenticación sin contraseña por email | P2 |
| [03-03-MFA-SMS-Email.md](03-03-MFA-SMS-Email.md) | Segundo factor por SMS o email OTP | P3 |
| [03-04-Social-Login-Adicionales.md](03-04-Social-Login-Adicionales.md) | GitHub, Microsoft, Apple, SAML | P3 |

## Dependencias

```
03-02 Magic Links ──→ requiere Notification/Outbox (ya existe)
03-03 MFA SMS ──────→ requiere interfaz SmsProvider (nueva)
03-04 Social Login ─→ requiere DelegateLogin adapter pattern (ya existe para Google)
03-01 WebAuthn ─────→ librería web-auth/webauthn-framework
```
