# Plan de Trabajo — Módulo de Descarga Automatizada de DTEs

**Sistema SaaS Contable El Salvador - Smartpyme**

Fecha: Marzo 2025  
Basado en: jira-task-modulo-dtes.docx + cursor-prompt-modulo-dtes.docx

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Decisiones de diseño](#2-decisiones-de-diseño)
3. [Fases de implementación](#3-fases-de-implementación)
4. [Almacenamiento S3](#4-almacenamiento-s3)
5. [Consideraciones adicionales](#5-consideraciones-adicionales)

---

## 1. Resumen ejecutivo

### Objetivo
Implementar un módulo que permita a los contadores conectar cuentas de correo (Gmail OAuth2, IMAP) para descargar automáticamente los DTEs recibidos del Ministerio de Hacienda (MH), validarlos y procesarlos en los módulos de Compras/IVA y Contabilidad.

### User Stories principales
- Conectar Gmail con OAuth2 sin compartir contraseña
- Conectar cuentas IMAP (Outlook, Yahoo, dominio propio)
- Descargar automáticamente JSON y PDF de DTEs
- Validar DTEs antes de inserción (estructura, NIT receptor, sello presente)
- Integrar DTEs válidos en Compras/IVA automáticamente
- Dashboard con estado de sincronizaciones, DTEs y errores
- Multiempresa: cuentas aisladas, tokens encriptados

### Stack técnico
| Capa | Tecnología |
|------|------------|
| Backend | Laravel 8, google/apiclient, webklex/laravel-imap, Horizon |
| Frontend | Angular 15 |
| Storage | Laravel Filesystem — disco `dtes` con S3 para respaldo |

---

## 2. Decisiones de diseño

### 2.1 Productos en compras
**Decisión:** Buscar producto por descripción. Si no se encuentra, dejar el DTE en **estado pendiente** para que el usuario asigne manualmente los productos antes de procesar.

- **Flujo:** Al parsear el DTE → buscar cada ítem por `descripcion` similar en el catálogo de productos de la empresa.
- **Si encuentra coincidencia:** Asignar `id_producto` al detalle de compra.
- **Si NO encuentra:** Marcar detalle como pendiente de clasificación; el DTE queda en estado "pendiente" hasta que el usuario asigne productos manualmente en la bandeja de DTEs.

### 2.2 Sucursal y Bodega
**Decisión:** Configurables en la **configuración de la cuenta de correo**.

- Cada `UserEmailAccount` tendrá campos opcionales: `id_sucursal`, `id_bodega`.
- Al crear Compra/Gasto desde un DTE descargado de esa cuenta, se usan estos valores.
- Si no están configurados: usar sucursal/bodega por defecto de la empresa (primera activa).

### 2.3 Inventario y Kardex
**Decisión:** Implementar **reglas configurables** por cuenta de correo.

- Opciones por cuenta: "Actualizar inventario" (sí/no) o "Modo solo contable".
- Si **modo solo contable**: no actualizar stock ni kardex; solo crear registro en Compras para Libro IVA.
- Si **actualizar inventario**: comportarse como compra normal (stock + kardex).

### 2.4 Mapeo DTE → tipo_documento
**Decisión:** Crear tabla `dte_tipo_mapeo` (o similar) para el mapeo de códigos MH a `tipo_documento` del sistema.

| Código MH | Tipo DTE | tipo_documento (compras/gastos) |
|-----------|----------|---------------------------------|
| 01 | Factura Consumidor Final | Factura |
| 03 | Crédito Fiscal | Crédito fiscal |
| 04 | Nota de Remisión | (definir) |
| 05 | Nota de Crédito | Nota de crédito |
| 06 | Nota de Débito | Nota de débito |
| 11 | Factura de Exportación | Factura de exportación |
| etc. | | |

- Tabla permite agregar nuevos tipos sin cambiar código.
- Incluir campos para distinguir si va a Compra o Gasto (por tipo de documento).

### 2.5 Validación de firma y sello
**Decisión:** Solo **documentar**; no implementar validación criptográfica.

- Documentar en README: qué campos se verifican (sello presente, estructura básica).
- No validar firma digital ni sello del MH criptográficamente en esta fase.
- Validaciones implementadas: estructura JSON, NIT receptor coincide, fecha en rango permitido.

### 2.6 Notificaciones
**Decisión:** Mostrar en el **dashboard** del módulo DTEs.

- Token revocado, errores de sync, DTEs con errores → visibles en el dashboard.
- No implementar notificaciones push/email adicionales en esta fase (o usar el sistema existente si ya hay uno de alertas).

### 2.7 Integración con Contabilidad
- **Compra/Gasto:** Crear registro; Libro IVA lee automáticamente de esas tablas.
- **NotifyAccountingModule:** Placeholder o logging; no lógica adicional por ahora.
- **Correlativos:** No incrementar para DTEs importados; usar `referencia`/`numero_control` del DTE.

### 2.8 Almacenamiento S3
- Disco `dtes` configurable (local/S3).
- Variable `DTE_STORAGE_DISK=s3` para producción.
- Ruta: `dtes/{id_empresa}/{year}/{month}/{dte_uuid}.json` (y `.pdf`).

---

## 3. Fases de implementación

### FASE 1 — Base de datos y modelos (2 días)

**Migraciones:**

1. **create_user_email_accounts_table**
   - Campos: `id`, `id_empresa`, `user_id`, `provider` (gmail, outlook, imap), `email`, `access_token` (encrypted), `refresh_token` (encrypted, nullable), `token_expires_at`, `imap_host`, `imap_port`, `imap_encryption`, `imap_user`, `imap_password` (encrypted, para IMAP), `id_sucursal` (nullable), `id_bodega` (nullable), `actualizar_inventario` (boolean, default false), `is_active`, `last_sync_at`, `created_at`, `updated_at`
   - Índice único: `(id_empresa, email, provider)`

2. **create_dte_documents_table**
   - Campos según documento original + `id_empresa` en lugar de `tenant_id`
   - Índice único: `(id_empresa, dte_uuid)`

3. **create_sync_logs_table**
   - Según documento original

4. **create_dte_tipo_mapeo_table** *(nuevo)*
   - `id`, `codigo_mh` (string, ej. "01", "03"), `nombre_tipo`, `tipo_documento` (string, ej. "Factura", "Crédito fiscal"), `destino` (enum: compra, gasto), `activo`, `created_at`, `updated_at`
   - Seeder con mapeo inicial

**Modelos:** UserEmailAccount, DteDocument, SyncLog, DteTipoMapeo

**Nota:** Usar `id_empresa` en todo el proyecto (convención existente).

---

### FASE 2 — OAuth2 Gmail + IMAP (3 días)

- GmailOAuthService, GmailAuthController
- ImapConnectionService (testConnection, saveAccount)
- Rutas API para cuentas de correo
- **Incluir en formulario IMAP:** host, port, encryption, user, password
- **Incluir en formulario cuenta:** `id_sucursal`, `id_bodega`, `actualizar_inventario` (checkbox)
- Tests unitarios

---

### FASE 3 — Motor de descarga y validación (4 días) ✅

- DteParserService, DteValidatorService (sin validación criptográfica de firma/sello)
- ProcessEmailAccountJob, ProcessDteJob
- GmailReaderService, ImapReaderService
- **Guardar JSON/PDF en disco `dtes`** (configurable S3)
- **Servicio de búsqueda de producto por descripción** para mapear ítems del DTE
- Si no hay coincidencia → DTE en estado "pendiente_clasificacion"
- Tests unitarios

---

### FASE 4 — Integración con módulos existentes (2 días) ✅

- **InsertDteIntoIvaModule:** Crear Compra o Gasto según `DteTipoMapeo.destino`
  - Usar `id_sucursal`, `id_bodega` de la cuenta de correo
  - Usar `actualizar_inventario` para decidir si afectar inventario/kardex
  - Buscar/crear proveedor por NIT (reutilizar lógica de GastosController)
  - Para compras: buscar producto por descripción; si pendiente, no insertar aún
- **NotifyAccountingModule:** Placeholder (o log)
- Evento DteValidated
- Endpoint `POST /email-accounts/{id}/sync`
- Laravel Scheduler: sync cada hora
- **Prevención duplicados:** `email_message_id`, índice único `(id_empresa, dte_uuid)`

---

### FASE 5 — Frontend Angular (3 días) ✅

**Módulo `dte-management`** en `views/dte-management/`:

1. **email-accounts:** Lista de cuentas, conectar Gmail, conectar IMAP
   - Modal IMAP: host, port, encryption, user, password, **sucursal**, **bodega**, **actualizar inventario**
   - Botón "Probar conexión"
2. **sync-dashboard:** Métricas, tabla sync_logs, **notificaciones** (errores, tokens revocados)
3. **dte-inbox:** Tabla DTEs, filtros, **acción "Clasificar" para DTEs pendientes**
4. **dte-detail:** Detalle, errores, botón reintentar; **asignación manual de productos** para pendientes

**Servicios:** email-account.service, dte-document.service, sync-log.service

---

### FASE 6 — QA y ajustes (2 días)

- Tests de integración
- Manejo de errores robusto
- Endpoints adicionales: GET /dtes, GET /dtes/{id}, download json/pdf, POST /dtes/{id}/reprocess, GET /sync-logs
- **README del módulo** con:
  - Variables .env (AWS, DTE_STORAGE_DISK, etc.)
  - Configuración OAuth2 Google
  - **Documentación de validación:** qué se valida (estructura, NIT, sello presente); qué NO se valida (firma criptográfica)
- Revisión final de archivos modificados vs creados

---

## 4. Almacenamiento S3

### Configuración en `config/filesystems.php`
```php
'dtes' => [
    'driver' => env('DTE_STORAGE_DRIVER', 'local'),
    // Si es s3:
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    // Ruta prefix dentro del bucket
    'root' => env('AWS_DTES_PREFIX', 'dtes'),
],
// O disco local para desarrollo:
'dtes_local' => [
    'driver' => 'local',
    'root' => storage_path('app/dtes'),
],
```

### Variables .env
```
# DTE Storage (local | s3)
DTE_STORAGE_DRIVER=s3
# Si usa S3, reutilizar AWS_* existentes
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=
```

### Ruta de archivos
- `{root}/{id_empresa}/{year}/{month}/{dte_uuid}.json`
- `{root}/{id_empresa}/{year}/{month}/{dte_uuid}.pdf`

---

## 5. Consideraciones adicionales

### Tabla `dte_tipo_mapeo` — estructura sugerida
```sql
id, codigo_mh, nombre_tipo, tipo_documento, destino (compra|gasto), activo, created_at, updated_at
```

### Flujo DTE pendiente de clasificación
1. DTE descargado → ítems no matchean con productos
2. Se guarda en `dte_documents` con `processing_status = 'pendiente_clasificacion'`
3. En bandeja de DTEs, usuario ve fila con chip "Pendiente clasificación"
4. Clic "Clasificar" → modal con lista de ítems del DTE
5. Usuario asigna cada ítem a un producto (búsqueda/select)
6. Al guardar → se crea Compra con detalles correctos → `processing_status = 'processed'`

### Definición de Done (original)
- Código revisado en PR
- Tests unitarios en Jobs y validación
- Tests de integración OAuth → descarga → inserción IVA
- 4 pantallas Angular completas
- README actualizado
- Sin regresiones en Contabilidad / Libro IVA
- Probado con correos reales en ambiente certificación MH

---

## Anexo: Configuración Gmail OAuth (Fase 2)

1. **Google Cloud Console:** Crear proyecto → APIs & Services → Credentials → Create OAuth 2.0 Client ID
2. **Tipo:** Web application
3. **Authorized redirect URIs:** `https://tu-dominio-api/api/email-accounts/gmail/callback`
4. **Scopes:** `https://www.googleapis.com/auth/gmail.readonly`, `email`
5. **Variables .env:**
   ```
   GOOGLE_GMAIL_CLIENT_ID=xxx.apps.googleusercontent.com
   GOOGLE_GMAIL_CLIENT_SECRET=xxx
   GOOGLE_GMAIL_REDIRECT_URI=https://api.smartpyme.test/api/email-accounts/gmail/callback
   ```

---

*Documento vivo — actualizar conforme avance el desarrollo.*
