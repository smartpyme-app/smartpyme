# Recepción de DTE por reenvío automático

**Fecha:** 2026-09-08  
**Estado:** implementado (pipe, consumer, UI, purge, API).  
**Dominio de ingest:** `ingest.smartpyme.site`  
**Fuente de verdad:** working tree actual.

Este documento describe **cómo está construido** el método 2 (reenvío). No reemplaza Gmail OAuth ni IMAP. La guía para el cliente está en `Docs/GUIA_CLIENTE_RECEPCION_DTE_REENVIO.md`. El diagnóstico original está en `Docs/AUDITORIA_RECEPCION_DTE_EMAIL.md`.

No imprime secretos.

---

## 1. Qué hace

El cliente reenvía correos con DTE a `token@ingest.smartpyme.site`. SmartPyme **no lee su bandeja**. El token identifica la empresa. El parseo, validación y bandeja son los mismos que Gmail/IMAP (`DteEmailAttachmentHelper` → `ProcessDteJob` → `dte_documents`).

Gmail OAuth (`gmail.readonly`) se mantiene. Este método existe para no exigir CASA AL1 a quien no quiera dar acceso a su correo.

---

## 2. Flujo

```
SMTP :25
  → Exim (solo ingest.smartpyme.site, Local Mail Exchanger)
  → Default Address / catch-all
  → bin/dte-ingest-pipe.sh → php -q bin/dte-ingest-pipe.php
  → storage/app/mail-ingest/incoming/{id}.eml + .meta.json
  → artisan dte:ingest-spool (cada minuto)
      1. resolver token @ingest.smartpyme.site (nunca header.To)
      2. email_inboxes + cuenta sintética provider=forward
      3. ¿verificación Gmail? → guardar código/enlace, borrar .eml
      4. ¿inbox pausado? → failed/ (inbox_inactive)
      5. DteEmailAttachmentHelper + ProcessDteJob::dispatchSync
      6. éxito / no_dte → borrar .eml
  → artisan dte:ingest-purge --days=7 (diario 03:20)
```

Tenant = `email_inboxes.id_empresa` vía token. El `To:` de un forward de Gmail suele ser el correo original del cliente; **nunca** se usa como tenant.

---

## 3. Componentes

| Pieza | Ruta | Notas |
|---|---|---|
| Pipe (sin Laravel) | `Backend/bin/dte-ingest-pipe.php` | STDIN en chunks, tope 15 MB (`DTE_INGEST_MAX_BYTES`) |
| Entrypoint Exim | `Backend/bin/dte-ingest-pipe.sh` | **Obligatorio en prod.** Si se pipea el `.php`, cPanel lo corre como CGI → `Content-type: text/html` → bounce permanente |
| Resolver | `Backend/app/Services/MailIngest/IngestRecipientResolver.php` | Solo dominio `ingest.smartpyme.site`. Primario: argv, `RECIPIENT`, `LOCAL_PART`+`DOMAIN`, Delivered-To, X-Original-To, Envelope-To, X-Envelope-To. `To` al final |
| Parser MIME | `Backend/app/Services/MailIngest/InboundMimeParser.php` | Adjuntos para el helper existente |
| Detector Gmail | `Backend/app/Services/MailIngest/GmailForwardingVerificationDetector.php` | Extrae código/enlace. **No** hace click |
| Consumer | `Backend/app/Services/MailIngest/IngestMailService.php` | Spool → job DTE |
| Inboxes | `Backend/app/Services/MailIngest/EmailInboxService.php` | Crear / pausar / reanudar / regenerar / desactivar |
| Purge | `Backend/app/Services/MailIngest/IngestSpoolPurge.php` | Borra `.eml`/`.meta` > N días en incoming/processing/failed |
| API | `Backend/app/Http/Controllers/Api/DteManagement/EmailInboxController.php` | JWT + `descarga-automatizada-dtes` |
| UI | `/dte-management/cuentas` | Tarjeta encima de Conectar Gmail / IMAP |
| Front service | `Frontend/src/app/services/dte-management/email-inbox.service.ts` | |

`ProcessDteJob`, Gmail OAuth/scopes, IMAP y Exim global **no se tocan**.

---

## 4. Datos

### `email_inboxes`

Una fila activa o pausada por empresa (`purpose=dte`). Token 32 hex (`bin2hex(random_bytes(16))`), email `{token}@ingest.smartpyme.site`.

Estados: `ACTIVE`, `PAUSED`, `DISABLED`. Regenerar = desactivar la fila vieja + crear otra (el cliente debe actualizar el filtro).

Columnas de verificación Gmail: `verification_code`, `verification_link`, `verification_received_at` (migración `2026_09_08_120000_add_verification_to_email_inboxes`).

### `user_email_accounts` `provider=forward`

Cuenta sintética exigida por `ProcessDteJob` (FK NOT NULL). **Oculta** en el listado de cuentas y excluida de `dte:sync-accounts` / `ProcessEmailAccountJob`.

---

## 5. API

Prefijo: `email-inboxes`. Mismo middleware que cuentas: `jwt.auth` + `verificar.funcionalidad:descarga-automatizada-dtes`. Tenant = `id_empresa` del usuario auth.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/` | Inbox actual o `null` |
| POST | `/` | Activar (o reanudar si estaba pausado) |
| POST | `/{id}/pause` | Pausar |
| POST | `/{id}/resume` | Reanudar |
| POST | `/{id}/regenerate` | Nueva dirección |
| DELETE | `/{id}` | Desactivar |

Rutas: `Backend/routes/modulos/dte-management/email-accounts.php`.

---

## 6. Retención

| Resultado | `.eml` |
|---|---|
| `processed`, `no_dte`, `gmail_verification` | Se borra al instante |
| `unknown_token`, `inbox_inactive`, `empresa_inactive`, overflow del pipe | `failed/` + meta |
| Archivos viejos en incoming/processing/failed | `dte:ingest-purge --days=7` diario 03:20 |

Permisos: dirs spool `0700`, archivos `0600`, pipe `0700`.

---

## 7. Schedule (`Backend/app/Console/Kernel.php`)

```
dte:ingest-spool --limit=50     everyMinute, withoutOverlapping(5)
dte:ingest-purge --days=7       dailyAt 03:20
dte:sync-accounts --dias=30     hourly (solo Gmail/IMAP; excluye forward)
```

---

## 8. Comandos artisan

```
php artisan dte:inbox-create {id_empresa} [--user_id=] [--token=prueba123]
php artisan dte:ingest-spool [--limit=50]
php artisan dte:ingest-purge [--days=7]
```

`--token=` solo para pruebas. En prod el token es aleatorio (UI o comando sin flag).

---

## 9. Deploy (VPS)

Backend en prod: `/home/smartpyme/repositories/unificado/Backend/`.

1. Desplegar código (Backend + Frontend).
2. `php artisan migrate` — incluye columnas de verificación. **No** hace falta recrear el inbox si ya existe.
3. Confirmar que Exim/cPanel Default Address apunta a `bin/dte-ingest-pipe.sh`, no al `.php`.
4. Confirmar cron de Laravel (`schedule:run`) para spool + purge.
5. Probar desde UI: **Activar reenvío automático** en `/dte-management/cuentas`.
6. Verificación Gmail: el banner aparece cuando el detector guarda código/enlace. El cliente confirma en Gmail; SmartPyme no hace click.
7. Import de DTE JSON/XML real: cuando un proveedor mande un correo de Hacienda. PDF-only **no** importa (mismo techo que Gmail).

Rollback: quitar el pipe/catch-all de Exim; pausar o desactivar inboxes. Gmail/IMAP siguen.

---

## 10. Seguridad

- Token 128 bits; adivinar uno inyecta basura en **esa** empresa, no en otra.
- Resolución solo si el dominio es `ingest.smartpyme.site`.
- El remitente no elige tenant.
- El pipe no abre Laravel ni DB.
- Overflow: solo meta en `failed/`, `exit 0` (no bounce en bucle).
- No se loguea cuerpo ni JSON DTE completo.

---

## 11. Techos conocidos

- PDF sin JSON/XML: no hay DTE (sin OCR).
- Gmail no reenvía spam.
- Workspace/M365 puede prohibir reenvío externo (`5.7.520`).
- Inbox pausado: el correo llega al spool y se rechaza (`inbox_inactive`); no se “guarda para después”.
- El detector Gmail no auto-confirma. Si SpamAssassin tira el correo de Google, el cliente no ve el código.

---

## 12. Checks

```
php Backend/tests/Unit/MailIngest/dte_ingest_pipe_check.php
php Backend/tests/Unit/MailIngest/ingest_phase2_check.php
php Backend/tests/Unit/MailIngest/gmail_verification_and_purge_check.php
```

Sin PHPUnit. Fallan con `RuntimeException`.
