# Auditoría — Recepción de DTE por correo

**Fecha:** 2026-09-04  
**Estado:** diagnóstico y propuesta. Sin cambios de código de aplicación.  
**Alcance:** módulo actual de descarga automatizada de DTEs + diseño de recepción por reenvío.  
**Fuente de verdad:** working tree actual. No se revivió código de ramas o commits antiguos.

Este documento no imprime secretos. Si una variable o archivo existe, se indica la ruta, no el valor.

---

## 1. Resumen del funcionamiento actual

El módulo **Descarga automatizada de DTEs** (`funcionalidad` slug `descarga-automatizada-dtes`) ya está implementado y en uso.

Permite a una empresa conectar una o más cuentas de correo, buscar mensajes con adjuntos DTE, parsearlos, validarlos y dejarlos en la bandeja `/dte-management/dtes` para revisión humana. La inserción automática en Compras/Gastos está **desactivada a propósito** cuando el DTE queda en `processing_status = pending` (comentario `ponytail` en `InsertDteIntoIvaModule`).

Hoy existen **tres orígenes de correo**, no dos:

| Método | Estado | ¿SmartPyme lee la bandeja del cliente? |
|---|---|---|
| Gmail OAuth + Gmail API | Implementado y expuesto en UI | Sí (`gmail.readonly`) |
| IMAP genérico (“Conectar Otros”) | Implementado y expuesto en UI | Sí (credenciales IMAP) |
| Outlook OAuth | Reservado en el enum de BD (`outlook`) | No implementado |

El cliente ya puede evitar Gmail API usando IMAP. IMAP **también** exige acceso a la bandeja (usuario + contraseña). No resuelve el problema de Google CASA, y tampoco el de “no queremos acceso a toda la bandeja”.

La sincronización es **pull**: cron horario + botón manual. Ambos ejecutan el job **en el mismo proceso** (`dispatchSync`), no en Redis.

---

## 2. Arquitectura actual

```
Cliente (Angular /dte-management/cuentas)
        |
        | JWT + funcionalidad:descarga-automatizada-dtes
        v
EmailAccountController / GmailAuthController
        |
        +-- Gmail OAuth --> user_email_accounts (provider=gmail, tokens cifrados)
        +-- IMAP          --> user_email_accounts (provider=imap, password cifrada)
        |
        v
ProcessEmailAccountJob   (hoy: dispatchSync)
        |
        +-- GmailReaderService  implements EmailReaderInterface
        +-- ImapReaderService   implements EmailReaderInterface
        |
        v
DteEmailAttachmentHelper::groupAttachments()
        |
        v
ProcessDteJob            (hoy: dispatchSync)
        |
        +-- DteDocumentParseService  (JSON SV | XML CR)
        +-- DteValidatorService
        +-- DteProductSearchService
        +-- storage disk `dtes`
        +-- dte_documents (unique id_empresa + dte_uuid)
        |
        v
DteValidated event
        +-- InsertDteIntoIvaModule   (no inserta si pending)
        +-- NotifyAccountingModule   (solo log)
        |
        v
Bandeja DTE / Dashboard de sync_logs
```

Puntos estructurales importantes:

- El contrato de entrada al pipeline es un arreglo normalizado (`email_message_id`, `source_format`, `source_content`, `pdf_content`, `acuse_content`).
- `ProcessDteJob` **exige** un `UserEmailAccount`. `dte_documents.user_email_account_id` es NOT NULL + FK.
- El aislamiento multiempresa está en scopes globales (`id_empresa` del usuario autenticado) y en el unique `(id_empresa, dte_uuid)`.
- No hay dominio `ingest.*`, ni pipe SMTP, ni tabla de direcciones virtuales.

---

## 3. Componentes encontrados

### Backend — Gmail / correo

| Archivo | Rol |
|---|---|
| `Backend/app/Services/Gmail/GmailOAuthService.php` | URL OAuth, callback, refresh token |
| `Backend/app/Services/Gmail/GmailReaderService.php` | Lista mensajes Gmail, baja adjuntos json/pdf/xml |
| `Backend/app/Http/Controllers/Api/DteManagement/GmailAuthController.php` | `redirect` (JWT) + `callback` (público, redirect al front) |
| `Backend/app/Services/Imap/ImapReaderService.php` | Lectura IMAP INBOX |
| `Backend/app/Services/Imap/ImapConnectionService.php` | Test + persistencia IMAP |
| `Backend/app/Contracts/EmailReaderInterface.php` | Contrato pull por rango de fechas |
| `Backend/app/Http/Controllers/Api/DteManagement/EmailAccountController.php` | CRUD cuentas, sync, notificaciones |
| `Backend/app/Jobs/ProcessEmailAccountJob.php` | Orquesta reader → temp files → ProcessDteJob |
| `Backend/app/Console/Commands/SyncDteEmailAccounts.php` | `dte:sync-accounts --dias=30` |
| `Backend/app/Console/Kernel.php` | Schedule horario de ese comando |

### Backend — procesamiento DTE (reutilizable)

| Archivo | Rol |
|---|---|
| `Backend/app/Support/Dte/DteEmailAttachmentHelper.php` | Agrupa adjuntos JSON/XML/PDF/acuse |
| `Backend/app/Jobs/ProcessDteJob.php` | Parse, valida, guarda, idempotencia |
| `Backend/app/Services/Dte/DteDocumentParseService.php` | JSON MH o XML DGT → estructura común |
| `Backend/app/Services/Dte/DteParserService.php` | Parser JSON El Salvador |
| `Backend/app/Services/Dte/DteValidatorService.php` | Estructura, NIT receptor, sello MH, antigüedad |
| `Backend/app/Services/Dte/DteProductSearchService.php` | Match de ítems → `pendiente_clasificacion` |
| `Backend/app/Events/DteValidated.php` | Evento post-validación |
| `Backend/app/Listeners/InsertDteIntoIvaModule.php` | Puente a Compras/IVA (hoy no auto-inserta pending) |
| `Backend/app/Listeners/NotifyAccountingModule.php` | Placeholder de log |
| `Backend/app/Http/Controllers/Api/DteManagement/DteDocumentController.php` | Bandeja, detalle, procesar |
| `Backend/app/Http/Controllers/Api/DteManagement/SyncLogController.php` | Historial de sync |

### Modelos y migraciones

| Archivo | Rol |
|---|---|
| `Backend/app/Models/DteManagement/UserEmailAccount.php` | Cuenta de correo; tokens/password cifrados |
| `Backend/app/Models/DteManagement/DteDocument.php` | DTE recibido |
| `Backend/app/Models/DteManagement/SyncLog.php` | Corrida de sync |
| `Backend/app/Models/DteManagement/DteTipoMapeo.php` | Código DTE → compra/gasto |
| `Backend/database/migrations/2026_03_20_100001_create_user_email_accounts_table.php` | Enum `gmail,outlook,imap` |
| `Backend/database/migrations/2026_03_20_100002_create_dte_documents_table.php` | Unique empresa+uuid |
| `Backend/database/migrations/2026_07_01_120001_add_dtes_duplicates_to_sync_logs.php` | Contador de duplicados |
| `Backend/database/migrations/2026_05_29_100000_add_notification_user_id_to_user_email_accounts_table.php` | Destinatario de avisos |

### Frontend

| Archivo | Rol |
|---|---|
| `Frontend/src/app/views/dte-management/dte-management.component.html` | Tabs: Cuentas / Dashboard / Bandeja |
| `Frontend/src/app/views/dte-management/email-accounts/*` | Conectar Gmail, IMAP, sync, desconectar |
| `Frontend/src/app/services/dte-management/email-account.service.ts` | API client |
| `Frontend/src/app/views/dte-management/dte-inbox/*` | Bandeja de DTEs |
| `Frontend/src/app/views/dte-management/sync-dashboard/*` | Historial de sincronizaciones |
| `Frontend/src/app/views/dte-management/dte-detail/*` | Detalle y proceso a compra/gasto |
| `Frontend/src/app/guards/funcionalidad.guard.ts` | `SLUG_DESCARGA_AUTOMATIZADA_DTES` |

### Configuración (nombres, no valores)

| Dónde | Claves |
|---|---|
| `Backend/.env.example` | `GOOGLE_GMAIL_CLIENT_ID`, `GOOGLE_GMAIL_CLIENT_SECRET`, `GOOGLE_GMAIL_REDIRECT_URI` |
| `Backend/config/services.php` → `gmail` | client_id, client_secret, redirect_uri, frontend_redirect (`DTE_FRONTEND_URL`) |
| `Backend/config/services.php` → `google` | `GOOGLE_ID` / `GOOGLE_SECRET` (login social; fallback del Gmail client) |
| `Backend/config/dte.php` | disco S3 / schedule de migración |
| `Backend/config/filesystems.php` → `dtes` | `DTE_STORAGE_DRIVER`, root `storage/app/dtes` |
| `Backend/config/queue.php` | default `QUEUE_CONNECTION` (fallback `sync`) |

`GOOGLE_GMAIL_CLIENT_SECRET` existe como variable. **No se documenta el valor.**

### Dependencias ya instaladas

- `google/apiclient` ^2.0
- `webklex/laravel-imap` ^6.2
- Laravel + Symfony Mime (sirve para parsear RFC822 **sin** paquete nuevo)
- Redis / Horizon: Horizon **no** está en `composer.json`. El plan original lo mencionaba; el código real usa `dispatchSync` + schedule.

### Documentación previa

- `Docs/PLAN-MODULO-DTES.md` — diseño original del módulo (marzo 2025). Sigue describiendo el pull Gmail/IMAP.

---

## 4. Flujo Gmail actual

1. Usuario con funcionalidad activa abre `/dte-management/cuentas` y pulsa **Conectar Gmail**.
2. Front llama `GET /api/email-accounts/gmail/redirect` (JWT).
3. `GmailOAuthService::getAuthorizationUrl()` arma un `state` = base64(JSON `{user_id, id_empresa}`), `access_type=offline`, `prompt=consent`.
4. Google redirige a `GET /api/email-accounts/gmail/callback` (**sin JWT**; ruta en `Backend/routes/api.php`).
5. Se intercambia el `code` por tokens. El email se obtiene de `users.getProfile('me')` (no People API).
6. `UserEmailAccount::updateOrCreate` por `(id_empresa, email, provider=gmail)`. Tokens se guardan cifrados.
7. Redirect al front `/dte-management/cuentas?gmail=success&email=...`.
8. Sync manual: `POST /email-accounts/{id}/sync` → cooldown 10 min → `ProcessEmailAccountJob::dispatchSync` (hasta 300 s).
9. Sync automático: `dte:sync-accounts --dias=30` cada hora, `withoutOverlapping(30)`.
10. Reader: query Gmail `after:Y/m/d before:Y/m/d has:attachment`, pagina de 50, extrae json/pdf/xml.
11. Cada DTE se escribe a `storage/app/temp/dtes` y entra a `ProcessDteJob`.

**Riesgo existente (no tocar ahora):** el `state` OAuth no está firmado. Un atacante que inicie OAuth podría alterar `id_empresa` en el callback. Queda como deuda; no forma parte de esta extensión.

---

## 5. Scopes utilizados

Definidos en `GmailOAuthService::createClient()`:

- `https://www.googleapis.com/auth/gmail.readonly` (`Gmail::GMAIL_READONLY`)
- `email`
- `https://www.googleapis.com/auth/userinfo.email`

`GmailReaderService::createAuthenticatedClient()` vuelve a pedir solo `GMAIL_READONLY`.

Ese scope es **Restricted** en Google Cloud. Es la causa directa de la evaluación CASA AL1 y de la pantalla “Google hasn't verified this app”.

No hay forma de bajar el scope y seguir listando adjuntos de la bandeja. El reenvío es la alternativa de producto, no un cambio de scope.

---

## 6. Cómo se procesan actualmente los documentos

1. **Detección de adjuntos:** solo extensiones `json`, `pdf`, `xml`.
2. **Agrupación (`DteEmailAttachmentHelper`):**
   - Si hay JSON → un ítem formato `json`. El PDF se adjunta si existe. **Varios JSON en el mismo correo: se queda el último.**
   - Si no hay JSON → un ítem por XML de comprobante CR, emparejado por clave (50 dígitos) con PDF y acuse (`MensajeHacienda`).
   - **Correo solo con PDF → cero DTEs.** No hay OCR ni parser de PDF.
3. **Idempotencia:**
   - Primero: existe `dte_documents` con mismo `id_empresa` + `email_message_id` → `duplicate`.
   - Después: unique `(id_empresa, dte_uuid)` captura el mismo DTE llegado por otro Message-ID.
4. **Parse:** XML → `DocumentoImportResolver` (CR). JSON → `DteParserService` (SV).
5. **Validación:** estructura, NIT receptor vs NIT de la empresa, sello MH en SV, fecha ≤ 1 año.
6. **Storage:** `{id_empresa}/{year}/{month}/{dte_uuid}.json|.xml|.pdf|-acuse.xml` en disco `dtes`.
7. **Estados:** `validation_status` pending/valid/invalid; `processing_status` pending / pendiente_clasificacion / processed / failed / anulado.
8. **Errores de parse:** se guarda un DTE inválido (no se pierde el intento).
9. **Reintentos:** `$tries = 3` en ambos jobs, pero al usarse `dispatchSync` el backoff de cola no aplica como en Redis.

---

## 7. Qué partes podemos reutilizar

Reutilizar tal cual:

- `DteEmailAttachmentHelper` — normalizador de adjuntos.
- `ProcessDteJob` — persistencia, validación, idempotencia por UUID.
- `DteDocumentParseService` / `DteParserService` / parsers CR.
- `DteValidatorService` — el NIT receptor es la segunda traba anti-cruce de empresas.
- `dte_documents`, bandeja, detalle, proceso a compra/gasto.
- `SyncLog` — se puede reutilizar o especializar para ingest.
- Disco `dtes` y layout de paths.
- Feature flag `descarga-automatizada-dtes`.
- Cifrado y scopes de `UserEmailAccount`.
- UI existente de Cuentas / Dashboard / Bandeja.

Reutilizar con un adaptador delgado:

- El **contrato de salida** de `EmailReaderInterface` (el array), no el interfaz en sí. El interfaz es pull-por-fechas; el reenvío es push-por-mensaje.

No reutilizar como mecanismo de ingest:

- `GmailReaderService` / OAuth.
- `ImapReaderService` contra el Gmail del cliente.
- El cron `dte:sync-accounts` (seguiría solo para Gmail/IMAP).

---

## 8. Qué partes necesitamos agregar

1. Identidad de recepción virtual por empresa (`email_inboxes`).
2. Generación / regeneración / pausa de token.
3. Receptor SMTP en el VPS (Exim/cPanel, no Postfix, no SES).
4. Parser de mensaje crudo RFC822 → adjuntos (Symfony Mime).
5. Resolución **solo** por destinatario `token@ingest...` → `empresa_id`.
6. Detector de correo de **verificación de reenvío de Gmail** (y equivalentes) para mostrarlo en UI.
7. Cola real para no bloquear el pipe de Exim.
8. Controles: tamaño, tipos, rate limit, empresa activa, inbox activo.
9. UI de elección de método + instrucciones + estado.
10. Logs/métricas de ingest (sin cuerpo de correo).
11. Documentación técnica y de cliente (fase posterior a esta auditoría).
12. Tests del nuevo camino.

---

## 9. Arquitectura propuesta

Principio: **extensión, no reconstrucción**.

```
                    +------------------+
                    |  Gmail OAuth     |  (sin cambios)
                    +--------+---------+
                             |
                    +--------v---------+
                    |  IMAP cliente    |  (sin cambios)
                    +--------+---------+
                             |
Proveedor --> correo cliente |
                    | filtro / reenvío
                    v
         token@ingest.smartpyme.app
                    |
                    v
         Exim / cPanel (catch-all + pipe)
                    |
                    v
         artisan dte:ingest-mail   (lee stdin, sale rápido)
                    |
                    v
         IngestMailService
            1. Extraer destinatario @ingest...
            2. Resolver email_inboxes.token
            3. Validar inbox + empresa
            4. ¿Es verificación Gmail? → guardar código, no parsear DTE
            5. Extraer adjuntos
            6. DteEmailAttachmentHelper
                    |
                    v
         ProcessDteJob  (mismo de hoy)
                    |
                    v
         dte_documents + bandeja
```

### Enfoque de datos (recomendado)

**Opción B del brief + puente mínimo al modelo actual.**

- Tabla nueva `email_inboxes` (identidad, token, estados, stats).
- Al activar, crear o reutilizar un `user_email_accounts` sintético `provider = forward`, `email = token@dominio`.
- `ProcessDteJob` no se reescribe. La FK `user_email_account_id` sigue válida.
- El tenant **nunca** se infiere del remitente. Solo del destinatario.

### Enfoques de recepción en VPS

| # | Enfoque | Pros | Contras | Recomendación |
|---|---|---|---|---|
| 1 | Catch-all + pipe Exim → artisan | Tiempo real, una cuenta, sin Postfix/SES | Hay que validar permisos/pipe en cPanel | **Preferido** |
| 2 | Un buzón catch-all + poll IMAP local | Reusa `ImapReaderService` | Latencia, más riesgo si se parsea mal el To: | Fallback si el pipe no es viable |
| 3 | Una cuenta cPanel real por empresa | Simple de entender | Miles de cuentas; el brief lo prohíbe | Descartado |

Amazon SES Inbound y Postfix quedan fuera de esta fase.

---

## 10. Diseño de base de datos

Preferencia: tabla propia. No hinchar `empresas`.

### `email_inboxes`

```
id
id_empresa                 FK empresas
user_email_account_id      FK user_email_accounts  (cuenta sintética)
token                      unique, no el id de empresa
email                      unique, token + dominio configurado
status                     NOT_CONFIGURED | PENDING_VERIFICATION | ACTIVE | PAUSED | DISABLED | ERROR
purpose                    default 'dte'   (futuro: otros tipos)
allow_from                 json nullable (allowlist de remitentes; vacío = cualquier)
max_message_bytes          nullable (override)
last_email_at
last_dte_at
last_error_at
last_error_message         texto corto, sin cuerpo de correo
emails_received
emails_rejected
dtes_imported
dtes_duplicates
verification_code          nullable (código Gmail extraído)
verification_received_at
created_by_user_id
revoked_at
created_at / updated_at
```

Índices: unique `token`, unique `email`, index `(id_empresa, status)`.

Reglas:

- Token: `bin2hex(random_bytes(16))` → 32 hex. El ejemplo `84f1d9c3e2` (10 hex, ~40 bits) es demasiado corto.
- Regenerar = desactivar fila anterior (`DISABLED` + `revoked_at`) y crear otra. No reutilizar el token.
- Varias filas por empresa quedan permitidas (`purpose` / historial).
- No usar `154@ingest...`.

### Cambio menor en `user_email_accounts`

Migración para ampliar el enum `provider` con `forward` (hoy: `gmail`, `outlook`, `imap`).

`outlook` se deja intacto; no se implementa OAuth Microsoft en esta fase.

### Qué no va en `empresas`

Ni token, ni dirección, ni stats. Una empresa puede tener 0..N inboxes a lo largo del tiempo.

---

## 11. Diseño backend

### Comando (entrada desde Exim)

`php artisan dte:ingest-mail {--recipient=} {--source=stdin}`

- Lee el raw MIME de STDIN (pipe).
- `--recipient` si Exim puede pasar el envelope To. Si no, se parsean `Delivered-To`, `X-Original-To`, `Envelope-To`, `To`.
- Valida tamaño **antes** de cargar el cuerpo completo en memoria (límite configurable, p.ej. 15 MB).
- Resuelve inbox. Si no existe / inactivo / empresa inactiva (`empresas.activo`) / sin funcionalidad → log + exit 0 (no rebotar en loop).
- Si es verificación de reenvío → persistir código/enlace y terminar.
- Si hay DTE → `DteEmailAttachmentHelper` → `ProcessDteJob::dispatch` (async si Redis está disponible).
- El pipe debe terminar en < 2 s. El parse pesado y MH van a la cola.

### Servicios nuevos (pocos)

- `InboundMimeParser` — Symfony Mime, sin dependencia nueva.
- `EmailInboxResolver` — token → inbox → empresa. Único lugar que decide el tenant.
- `GmailForwardingVerificationDetector` — subject/from conocidos de Google.
- `EmailInboxService` — activar, pausar, regenerar, stats.

### API autenticada (mismo feature flag)

```
GET    /email-inboxes
POST   /email-inboxes                 # activa / crea
POST   /email-inboxes/{id}/pause
POST   /email-inboxes/{id}/resume
POST   /email-inboxes/{id}/regenerate
DELETE /email-inboxes/{id}            # DISABLED, no borra historial
```

No endpoint público HTTP para “recibir correo”. El inbound es SMTP → pipe, no webhook.

### Idempotencia del ingest

Además del unique `dte_uuid`:

- Guardar `message_id` + hash SHA-256 del raw (o de cada adjunto) en un log de ingest por inbox.
- Un reenvío del mismo DTE con otro Message-ID sigue cayendo en `dte_documents_empresa_uuid_unique`.

### Multi-tenancy (cruce A→B)

Cadena obligatoria, en este orden:

1. Destinatario debe ser `*@{INGEST_DOMAIN}`.
2. Local-part = token de **una** fila.
3. Esa fila trae `id_empresa`. Nunca se usa otro dato para elegir empresa.
4. `ProcessDteJob` escribe `dte_documents.id_empresa` desde la cuenta sintética, que se creó con el mismo `id_empresa`.
5. `DteValidatorService` marca inválido si el NIT receptor ≠ NIT de esa empresa.

El remitente **no** elige tenant. Un atacante que adivine un token (por eso 128 bits) podría inyectar basura en esa empresa; no puede apuntar a otra.

---

## 12. Diseño frontend

Encaje: **la misma pestaña Cuentas** (`/dte-management/cuentas`). No crear un módulo suelto.

Propuesta de layout (estilo actual: cards blancas, botones primary/outline, tabla):

1. Bloque introductorio nuevo encima de la tabla:
   - Título: “Método de recepción de documentos”.
   - Texto: “Puedes conectar tu cuenta de Gmail directamente o utilizar reenvío automático. Con el reenvío SmartPyme no necesita acceder a tu bandeja de entrada.”
   - Dos opciones (no radio que oculte Gmail/IMAP ya conectados; son métodos coexistentes):
     - Mantener **Conectar Gmail** / **Conectar Otros**.
     - Nuevo **Activar reenvío automático**.
2. Si hay inbox:
   - Dirección + copiar.
   - Estado (punto + label).
   - Último correo / último DTE.
   - Banner de verificación Gmail si `PENDING_VERIFICATION`.
   - Acciones: instrucciones, pausar, regenerar, desactivar.
3. La tabla actual de cuentas Gmail/IMAP **no se toca** salvo mostrar `forward` si listamos la cuenta sintética. Preferible **no** listar la cuenta sintética ahí para no confundir; el inbox tiene su propia tarjeta.

Copy interno sugerido (adaptar al i18n `country.tax.fe.*` existente):

- “¿Cómo funciona?” / “¿Por qué usar reenvío automático?” / “Ver guía paso a paso”.
- Advertencia Gmail: “Gmail enviará un correo de confirmación a esta dirección. Cuando llegue, SmartPyme te mostrará el código o el enlace para que completes la verificación. Hasta entonces el reenvío no funciona.”

Clientes Gmail OAuth actuales: cero cambios de flujo.

---

## 13. Integración cPanel / Exim

Objetivo de producción:

```
SMTP :25/:587  →  Exim  →  router local del dominio ingest
                         →  catch-all / default address
                         →  pipe transport
                         →  php artisan dte:ingest-mail
```

No instalar Postfix. No crear miles de cuentas. No usar SES inbound.

### Modelo cPanel viable (a confirmar en el VPS)

1. Zona DNS `ingest.smartpyme.app` (o el dominio que se elija) con **MX hacia este VPS**.
2. Dominio o subdominio de correo en cPanel/WHM.
3. **Dirección por defecto / catch-all** del dominio: pipe a un programa, no buzón por token.
4. Alternativa equivalente: `/etc/valiases/{dominio}` con `*: "|/usr/bin/php .../artisan dte:ingest-mail"`.
5. Filtro de cuenta: más frágil (depende de una cuenta real). Solo si el catch-all pipe no está disponible.
6. Spam Filters: exceptuar o vigilar correos de `mailer-daemon@google.com` / verificación Gmail. Si SpamAssassin tira el confirmación, el método 2 no arranca para Gmail.
7. Email Deliverability (SPF/DKIM): relevante para **saliente**. El ingest es entrante; igual hay que aceptar correo de Gmail/Microsoft/proveedores.
8. Tamaño máximo de mensaje Exim vs límite de la app.

### Permisos típicos del pipe

El proceso corre como el usuario del paquete cPanel, no como `root`. Debe poder:

- Ejecutar el PHP CLI de la app (mismo major que Laravel).
- Leer `.env` / bootstrap.
- Escribir `storage/app/temp/dtes` y logs.
- Encolar a Redis (si aplica).

SELinux (AlmaLinux) puede bloquear que Exim ejecute PHP. Es el riesgo operativo #1 del pipe.

---

## 14. Qué debemos configurar / revisar en el VPS

**No ejecutar cambios.** Solo inventario. Comandos de lectura:

```bash
# Identidad
hostnamectl
cat /etc/os-release
rpm -q cpanel-letsencrypt 2>/dev/null; echo "---"

# Exim / cPanel
systemctl is-active exim
exim -bV
/usr/local/cpanel/cpanel -V
# Routers / transports (no editar)
exim -bP routers
exim -bP transports
exim -bP message_size_limit
# Si existe editor avanzado de Exim, solo lectura
ls -l /etc/exim.conf /etc/exim.conf.local /etc/exim.conf.localopts 2>/dev/null

# Dominios y aliases de correo
ls /etc/valiases/ 2>/dev/null
ls /etc/vdomainaliases/ 2>/dev/null
ls /etc/vfilters/ 2>/dev/null
# Catch-all del dominio candidato
grep -n "^\*" /etc/valiases/* 2>/dev/null

# DNS / MX (desde el VPS y desde fuera)
dig +short MX ingest.smartpyme.app
dig +short MX smartpyme.app
dig +short A ingest.smartpyme.app
ss -lntup | egrep ':25|:465|:587|:993'

# Firewall
firewall-cmd --list-all 2>/dev/null || iptables -L -n | head
# ¿CSF?
head -n 5 /etc/csf/csf.conf 2>/dev/null

# SELinux
getenforce
sestatus | head
# Denegados recientes relacionados con exim/php
ausearch -m avc -ts recent 2>/dev/null | egrep -i 'exim|php|pipe' | tail

# PHP CLI vs Apache
php -v
which php
/usr/local/bin/php -v 2>/dev/null
php -m | egrep -i 'mailparse|imap|redis'

# App Laravel
# (ajustar path real del deploy)
ls -ld /home/*/smartpyme/Backend /home/*/public_html 2>/dev/null
# Usuario dueño, no imprimir .env
stat -c '%U %G %a %n' /path/al/Backend/artisan
# Confirmar que existen las claves, no los valores
grep -E '^(QUEUE_CONNECTION|REDIS_|GOOGLE_GMAIL_|DTE_|MAIL_)' /path/al/Backend/.env | cut -d= -f1

# Cola / supervisor
systemctl is-active redis supervisor
supervisorctl status
ls /etc/supervisord.d/ /etc/supervisor/conf.d/ 2>/dev/null

# Antivirus / spam
rpm -q clamav clamd 2>/dev/null
# SpamAssassin / MailScanner
ps aux | egrep -i 'spamd|mailscanner|clam' | grep -v grep

# Logs recientes de correo (solo metadatos)
tail -n 50 /var/log/exim_mainlog
# ¿Hay dominio de correo ya usado por la app?
```

Datos que el equipo debe anotar (sin pegar secretos):

- Path real del backend en el VPS.
- Usuario cPanel y si PHP CLI es ea-php o el mismo que FPM.
- Dominio final: ¿`ingest.smartpyme.app` está libre? ¿Hay que crear subdominio?
- `QUEUE_CONNECTION` real (el repo default es `sync`).
- Si Supervisor ya corre `queue:work`.
- MX actual de `smartpyme.app` y si el correo corporativo vive en este mismo Exim.
- Límite de tamaño Exim.
- Si SELinux está Enforcing.

---

## 15. Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Google CASA bloquea Gmail OAuth a clientes nuevos | Método 1 degradado | Método 2; no borrar OAuth |
| Gmail no reenvía hasta verificar el destino | Método 2 inútil para Gmail | Capturar y mostrar el correo de verificación |
| Workspace/admin deshabilita forwarding | Cliente no puede usar reenvío | Documentar; IMAP/Gmail siguen |
| M365 política `5.7.520` bloquea reenvío externo | Igual | Documentar; pedir a TI habilitar forwarding o remote domain |
| Gmail **no reenvía spam** | DTEs caídos en spam del cliente no llegan | Decirlo en la guía; no es un bug nuestro |
| Pipe Exim + SELinux | Correos aceptados y no procesados | Checklist VPS; fallback IMAP local |
| Catch-all abierto a internet | Basura/spam al parser | Token largo, rate limit, tipos de archivo, no rebotar |
| Token corto / enumerable | Inyección a una empresa | 32 hex; no usar id de empresa |
| `dispatchSync` en pipe | Exim timeout, correo reintentado | Cola async en el path nuevo |
| Varios JSON / solo PDF | DTEs perdidos (ya ocurre hoy) | Documentar el techo actual; no inventar OCR |
| Confirmación Gmail a spam de cPanel | Usuario no puede verificar | Whitelist / detector + logs |
| Regenerar dirección | Cliente sigue reenviando a la vieja | UI clara; fila vieja DISABLED |
| Dominio MX mal apuntado | Cero recepción | Checklist DNS antes de beta |

---

## 16. Seguridad

### Token y enumeración

- `random_bytes(16)` hex, unique en BD.
- Respuestas idénticas hacia SMTP para token inexistente vs inbox pausado (exit 0, log interno). No “user unknown” selectivo que permita sondear tokens si eso genera backscatter; preferir aceptar y descartar.
- Rate limit por token y por IP SMTP / dominio remitente (contadores Redis).

### Tenant

- Empresa A NUNCA se selecciona por remitente, asunto o NIT del emisor.
- Empresa inactiva (`empresas.activo = false`) o sin funcionalidad: descartar.
- Inbox `PAUSED` / `DISABLED`: descartar, no procesar.

### Remitente

- Fase 1: no exigir allowlist (rompe proveedores nuevos).
- Campo `allow_from` listo para fase 2.
- El NIT receptor sigue siendo la validación de negocio.

### Archivos

- Allowlist: `json`, `xml`, `pdf` (igual que hoy).
- Límite de mensaje y de adjunto (config).
- No ejecutar macros; no guardar `.html` / `.js` / `.zip` en esta fase (un ZIP de DTE sería fase posterior).
- Antivirus: si ClamAV ya está en cPanel, usarlo en el pipe **solo si** el checklist VPS lo confirma. No instalar un motor nuevo “por si acaso”.
- No loguear JSON/PDF ni refresh tokens.

### Privacidad y retención

- Raw MIME: conservar poco tiempo (p.ej. 7–14 días) o no persistir el raw, solo adjuntos DTE en disco `dtes` (mismo régimen que hoy).
- Decisión pendiente: ¿guardar raw para reproceso?

### Auditoría

- Log estructurado: `inbox_id`, `id_empresa`, `message_id`, resultado, tamaños, tiempos. Sin cuerpo.

### Deuda existente (fuera de alcance salvo que se pida)

- `state` OAuth sin firma.
- Tokens Gmail/IMAP ya cifrados en atributos: bien. No rotarlos en esta tarea.

---

## 17. Pruebas

El path nuevo necesita tests propios. Hoy **no hay** tests de `ProcessDteJob` ni `ProcessEmailAccountJob`. Los tests existentes cubren parser, validador, helper de adjuntos y OAuth state inválido.

Matriz pedida → diseño:

| # | Caso | Resultado esperado |
|---|---|---|
| 1 | Empresa activa + DTE JSON/XML | `dte_documents` con ese `id_empresa` |
| 2 | Token / empresa inexistente | Descarte, sin fila |
| 3 | Inbox PAUSED/DISABLED | Descarte |
| 4 | Empresa `activo=false` | Descarte |
| 5 | Sin adjuntos | Rechazo `no_dte_attachments` |
| 6 | Solo PDF | Igual que hoy: 0 DTE (documentar; no es regresión nueva) |
| 7 | Solo JSON | 1 DTE |
| 8 | PDF + JSON | 1 DTE + pdf_path |
| 9 | Varios DTE XML CR | N documentos (helper actual) |
| 10 | Mismo `dte_uuid` | `duplicate` |
| 11 | JSON inválido | DTE invalid / parse_error |
| 12 | `.exe` / zip malicioso | Ignorado por extensión |
| 13 | Mensaje > max | Rechazo size |
| 14 | Parser throw | failed + log, pipe exit 0 |
| 15 | Redis caído | Reintento de job; no perder raw si se decide persistir spool |
| 16 | Reintento mismo Message-ID | duplicate |
| 17 | Dos empresas en paralelo | Cada token → su `id_empresa` |
| 18 | Alto volumen | Cola, rate limit, sin bloquear Exim |
| 19 | Spam sin DTE | Descarte |
| 20 | Token inválido | Descarte |

Más:

- Correo de verificación Gmail → `PENDING_VERIFICATION`, no DTE.
- Destinatario de empresa A en un mensaje que también copia empresa B: procesar **solo** el envelope/Delivered-To del pipe (un mensaje, un recipient).
- Cliente Gmail OAuth existente: tests de no-regresión (no tocar reader).

---

## 18. Roadmap

Alineado al brief, con un gate de VPS explícito.

| Fase | Qué | Criterio de salida |
|---|---|---|
| 1 | Esta auditoría | Documento revisado |
| 2 | Diseño cerrado (decisiones de la sección final) | Aprobación escrita |
| 3 | Migraciones `email_inboxes` + enum `forward` | Tests de modelo |
| 4 | Inventario VPS + pipe o fallback IMAP local | Correo de prueba llega al artisan |
| 5 | `IngestMailService` + resolver + detector Gmail | Tests unitarios |
| 6 | Dispatch a `ProcessDteJob` (async) | DTE aparece en bandeja |
| 7 | UI en Cuentas | Activar/copiar/pausar/regenerar |
| 8 | Docs técnica + guía cliente | Archivos en `Docs/` |
| 9 | Matriz de pruebas | Casos 1–20 |
| 10 | Beta interna | 1–2 empresas SmartPyme |
| 11 | Producción | Feature flag ya existente |

Las fases 8 (docs `RECEPCION_DTE_REENVIO_AUTOMATICO.md` y `GUIA_CLIENTE_...`) se escriben **después** de cerrar las decisiones y de probar el flujo real de verificación Gmail en el VPS. No inventar pantallas de cPanel que no hayamos visto.

---

## 19. Archivos que deberán modificarse

Solo cuando haya aprobación. Lista tentativa:

- `Backend/app/Models/DteManagement/UserEmailAccount.php` — fillable/`provider` `forward` si usamos cuenta sintética
- `Backend/app/Http/Controllers/Api/DteManagement/EmailAccountController.php` — no mezclar sync pull con ingest; como mucho ocultar cuentas `forward` en el listado
- `Backend/routes/modulos/dte-management/email-accounts.php` o archivo hermano de rutas
- `Backend/app/Console/Kernel.php` — no meter el ingest en el cron horario
- `Backend/.env.example` — `DTE_INGEST_DOMAIN`, límites (nombres, no secretos)
- `Backend/config/services.php` o `config/dte.php` — dominio y límites
- `Frontend/src/app/views/dte-management/email-accounts/email-accounts.component.ts`
- `Frontend/src/app/views/dte-management/email-accounts/email-accounts.component.html`
- `Frontend/src/app/services/dte-management/email-account.service.ts` (o servicio `email-inbox`)

---

## 20. Archivos nuevos que deberán crearse

- Migración `..._create_email_inboxes_table.php`
- Migración `..._add_forward_to_user_email_accounts_provider.php`
- `Backend/app/Models/DteManagement/EmailInbox.php`
- `Backend/app/Services/MailIngest/InboundMimeParser.php`
- `Backend/app/Services/MailIngest/EmailInboxResolver.php`
- `Backend/app/Services/MailIngest/IngestMailService.php`
- `Backend/app/Services/MailIngest/GmailForwardingVerificationDetector.php`
- `Backend/app/Http/Controllers/Api/DteManagement/EmailInboxController.php`
- `Backend/app/Console/Commands/IngestDteMail.php`
- Tests unitarios/feature del resolver, detector, parser, ingest
- Más adelante: `Docs/RECEPCION_DTE_REENVIO_AUTOMATICO.md`, `Docs/GUIA_CLIENTE_RECEPCION_DTE_REENVIO.md`

---

## 21. Dependencias nuevas necesarias

**Ninguna obligatoria** si usamos Symfony Mime (ya viene con Laravel) + Exim existente + `random_bytes`.

Opcionales, solo si el VPS lo demuestra:

- Extensión `mailparse` (no hace falta si Symfony Mime basta).
- ClamAV si ya está instalado; no añadir paquete PHP antivirus.

No añadir Amazon SES SDK. No añadir Postfix. No añadir otro cliente Google.

---

## 22. Qué NO debemos modificar

- `GmailOAuthService` / scopes / callback (salvo bug de seguridad pedido aparte).
- `GmailReaderService` y su query.
- Flujo UI **Conectar Gmail**.
- `ImapReaderService` / “Conectar Otros”.
- `DteParserService`, parsers CR, `DteDocumentParseService`.
- Lógica de bandeja / procesar a compra o gasto.
- `InsertDteIntoIvaModule` (la revisión manual se mantiene).
- Clientes que ya tienen `provider=gmail` o `imap`.
- No reescribir `ProcessDteJob` “para dejarlo bonito”.
- No copiar lógica de ramas viejas.
- No crear cuentas Gmail ni cuentas cPanel por empresa.

---

## Observabilidad (propuesta)

Logger channel `dte-ingest` (o el stack actual):

- `mail.received` / `mail.rejected` / `mail.processed`
- `dte.detected` / `dte.imported` / `dte.duplicate` / `dte.failed`
- campos: `inbox_id`, `id_empresa`, `message_id`, `duration_ms`, `reason`
- nunca: cuerpo, JSON DTE completo, tokens OAuth, passwords

Métricas (contador Redis o logs agregables): recibidos, rechazados por motivo, importados, duplicados, errores, p95 de ingest.

`sync_logs` puede registrar corridas de ingest como `provider=forward` o una tabla `email_inbox_events` si no queremos mezclar pull y push. Decisión pendiente.

---

## Reenvío real en Gmail y Outlook (para no inventar pasos)

Fuentes oficiales consultadas (2026):

- [Reenviar mensajes de Gmail](https://support.google.com/mail/answer/10957)
- [Gmail API forwarding](https://developers.google.com/workspace/gmail/api/guides/forwarding_settings)
- [Outlook.com forwarding](https://support.microsoft.com/en-us/outlook/turn-on-or-off-automatic-forwarding-in-outlook-com)
- [M365 external forwarding / 5.7.520](https://learn.microsoft.com/en-us/defender-office-365/outbound-spam-policies-external-email-forwarding)

### Gmail (cuenta personal)

Hay **dos** mecanismos. Ambos exigen que el destino esté **verificado**.

1. **Reenvío general** (Settings → See all settings → Forwarding and POP/IMAP → Add a forwarding address).
   - Gmail envía un **enlace de verificación** (y código) al destino.
   - Hasta confirmar, no reenvía.
   - Reenvía **todos** los correos nuevos **excepto spam**.
   - La primera semana Gmail muestra un aviso de que el reenvío está activo.
2. **Filtro** (“Forward it to”).
   - El destino tiene que estar ya verificado; si no, no aparece en la lista del filtro.
   - Reenvía solo lo que cumple el criterio.
   - Documentación oficial: para reenviar solo algunos, **apagar** el reenvío general y usar filtro.

Google Workspace: un admin puede prohibir el forwarding. En ese caso el cliente no puede usar el método 2 con Gmail.

**Implicación de producto:** SmartPyme debe recibir el correo de verificación y mostrar el código/enlace en la tarjeta del inbox. Sin eso, Gmail no reenvía.

### Outlook.com (personal)

- Settings → Mail → Forwarding → Enable forwarding → dirección → Save.
- Microsoft pide **verificación de identidad / 2FA** al activar forwarding, no un correo de confirmación al destino como Gmail.
- Forward vs regla de **redirect**: el redirect conserva remitente original; el forward aparece como reenviado.

### Microsoft 365 / correo corporativo

- El usuario puede crear una regla “Forward to” / “Redirect to”.
- TI puede bloquear reenvío externo. NDR típico: `5.7.520 ... organization does not allow external forwarding`.
- Eso no lo resuelve SmartPyme. La guía debe decir: “Pida a su administrador que permita reenvío externo a `ingest.smartpyme.app`, o use Gmail OAuth / IMAP.”

### Qué reenviar (recomendación de producto, no un filtro mágico único)

- Correos **con adjunto** `.json`, `.xml` o `.pdf` de facturación electrónica.
- No reenviar toda la bandeja (Gmail: usar filtro, no forwarding global).
- Los marcados como spam en Gmail **no** se reenvían.

La guía de cliente (fase 8) debe copiar estos pasos oficiales, no un recetario inventado. Falta validar en UI real de Gmail/Outlook el wording exacto en español en el momento de escribir la guía.

---

## Rollout y compatibilidad

```
Cliente A  Gmail OAuth     ──┐
Cliente B  Reenvío         ──┼──► ProcessDteJob ──► dte_documents
Cliente C  Gmail OAuth     ──┤
Cliente D  Reenvío         ──┤
Cliente E  IMAP (ya existe)──┘
```

Nada del método 1 se apaga. El feature flag actual cubre los tres. Beta: empresas internas primero.

Rollback: desactivar catch-all/pipe; los inboxes quedan PAUSED; Gmail/IMAP siguen. Migraciones son aditivas.

---

## DECISIONES PENDIENTES

Hay que cerrar estas antes de implementar.

### D1. Dominio de recepción

¿`ingest.smartpyme.app` (subdominio nuevo + MX) o un dominio/alias que ya reciba correo en este Exim?

### D2. Mecanismo VPS

¿Intentamos **pipe Exim/catch-all** como plan A y IMAP local como plan B, o preferís empezar por el buzón único + poll para reducir riesgo de SELinux?

Recomendación: plan A pipe, con gate de inventario VPS antes de escribir el comando.

### D3. Modelo de datos

¿Confirmamos `email_inboxes` + `user_email_accounts` sintético `provider=forward` para no tocar `ProcessDteJob`?

Alternativa más limpia y más invasiva: hacer `user_email_account_id` nullable y pasar solo `id_empresa` al job.

Recomendación: sintético (menos riesgo).

### D4. Cola

¿En producción `QUEUE_CONNECTION` ya es Redis + Supervisor? Si sigue en `sync`, el pipe no es seguro. ¿Podemos exigir Redis solo para ingest?

### D5. Verificación Gmail

¿Auto-extraer código y mostrarlo en UI, o también intentar abrir el enlace de confirmación automáticamente?

Recomendación: **solo mostrar** código/enlace. Auto-click es frágil y parece sospechoso a Google.

### D6. ¿Gmail y reenvío a la vez?

¿Una empresa puede tener Gmail OAuth **y** inbox de reenvío activos? El unique `dte_uuid` evita duplicados.

Recomendación: sí, permitir ambos.

### D7. Retención del raw MIME

¿Spool 7 días para reprocesar, o solo adjuntos DTE como hoy?

Recomendación: spool corto en disco local, no S3, con purge.

### D8. Allowlist de remitentes en v1

¿Cualquier remitente o lista blanca desde el día 1?

Recomendación: cualquiera en v1; campo listo.

### D9. Antivirus

¿Exigir ClamAV en v1 o solo si el VPS ya lo tiene?

Recomendación: no bloquear v1 por ClamAV.

### D10. PDF-only

¿Documentamos que no se importa, o se pide OCR/parser PDF en el mismo proyecto?

Recomendación: fuera de alcance. Mismo techo que Gmail hoy.

### D11. ¿Ocultar IMAP?

El brief habla de dos métodos. IMAP ya está. ¿Lo dejamos como “Conectar Otros”?

Recomendación: no tocarlo.

### D12. ¿Quién opera el DNS/MX?

¿El equipo de infra crea `ingest.smartpyme.app` o lo hacemos como parte de la implementación?

### D13. Límite de tamaño

¿15 MB de mensaje / 10 MB por adjunto es aceptable?

### D14. Documentos de fase 8

¿Los escribimos solo después del inventario VPS + un correo de verificación Gmail real?

Recomendación: sí.

---

## Criterio para pasar a implementación

1. Este documento revisado.
2. Decisiones D1–D14 (o un subconjunto mínimo: D1, D2, D3, D5, D6).
3. Inventario VPS (sección 14) adjunto como notas, sin secretos.
4. Orden explícita: **no** empezar por un refactor del módulo Gmail.
