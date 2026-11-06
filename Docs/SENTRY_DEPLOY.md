# Sentry en producción — guía paso a paso

Org: **smartpyme** → [https://smartpyme.sentry.io](https://smartpyme.sentry.io)

Hay **tres cosas distintas** (no confundirlas):

| Qué es | Para qué | Dónde se configura | ¿Secreto? |
|--------|----------|-------------------|-----------|
| **DSN Backend** | Que Laravel envíe errores a Sentry | `.env` del servidor API | Sí (no commitear `.env`) |
| **DSN Frontend** | Que Angular envíe errores a Sentry | `environment.prod.ts` (en el repo) | Público (va en el JS del navegador) |
| **Auth Token** | Subir **source maps** al hacer `ng build` | CI o `.env.sentry-build-plugin` en la máquina de build | Sí — **no** va en el servidor web ni en Laravel |

Sin DSN → la app **no reporta** errores.  
Sin Auth Token → la app **sí reporta**, pero los stacks pueden salir **minificados** (ilegibles).

---

## Paso 0 — Confirmar que el merge está desplegado

En el código desplegado deberías tener:

- **Backend:** `sentry/sentry-laravel` en `composer.json`, archivo `config/sentry.php`
- **Frontend:** `@sentry/angular` en `package.json`, `src/app/sentry.init.ts`, bloque `sentry` en `environment.prod.ts`

Si no está, hacé `git pull` de la rama donde mergeaste y volvé a desplegar.

---

## Paso 1 — Proyectos en Sentry (cuenta)

1. Entrá a [smartpyme.sentry.io](https://smartpyme.sentry.io).
2. Deben existir (o crearlos):
   - **`smartpyme-backend`** (PHP / Laravel)
   - **`smartpyme-frontend`** (JavaScript / Angular)

---

## Paso 2 — DSN del Backend (Laravel)

### Dónde lo conseguís en Sentry

1. Proyecto **smartpyme-backend**
2. **Settings** (engranaje del proyecto)
3. **Client Keys (DSN)**  
   Link directo: [settings/projects/smartpyme-backend/keys](https://smartpyme.sentry.io/settings/projects/smartpyme-backend/keys/)

Copiá la URL que empieza con `https://` y termina con un número de proyecto.

### Dónde lo pegás

En el **`.env` del servidor** donde corre la API (producción y/o staging):

```env
SENTRY_LARAVEL_DSN=https://TU_CLAVE@o4512296240807936.ingest.us.sentry.io/4512296255946752
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_ENVIRONMENT=production
```

(Ajustá el DSN al que te muestre Sentry; el de arriba es solo ejemplo de formato.)

### Qué hacer en el servidor

```bash
cd Backend
composer install --no-dev --optimize-autoloader
php artisan config:clear
# si usan config:cache en prod, después: php artisan config:cache
php artisan sentry:test
```

Revisá en Sentry → proyecto **smartpyme-backend** que llegó el evento de prueba.

**Importante:** `APP_DEBUG=false` y `APP_ENV=production` en prod.  
Workers (`queue:work`) y cron deben usar el **mismo** `.env`.

---

## Paso 3 — DSN del Frontend (Angular)

### Dónde lo conseguís en Sentry

1. Proyecto **smartpyme-frontend** (no el de backend)
2. **Settings → Client Keys (DSN)**  
   Link: [settings/projects/smartpyme-frontend/keys](https://smartpyme.sentry.io/settings/projects/smartpyme-frontend/keys/)

### Dónde lo pegás

En el repo, archivo **`Frontend/src/environments/environment.prod.ts`**, bloque `sentry` (viene del PR), por ejemplo:

```typescript
sentry: {
  enabled: true,
  dsn: 'https://TU_CLAVE@o4512296240807936.ingest.us.sentry.io/4512296255881216',
  environment: 'production',
  release: '',
  tracesSampleRate: 0.1,
  tracePropagationTargets: [
    /^https:\/\/apiunificado\.smartpyme\.site/,
    /^https:\/\/api.*\.smartpyme\.site/,
  ],
},
```

En **`environment.ts`** (dev) debe quedar `enabled: false` para no ensuciar Sentry en local.

### Qué hacer para producción

1. Commit/deploy con el DSN correcto en `environment.prod.ts` (o confirmar que el PR ya lo trae).
2. Build de producción y publicar `dist/Frontend` como siempre.

**No** hace falta poner el DSN del FE en el `.env` del servidor nginx/S3: va **dentro del bundle** al compilar.

---

## Paso 4 — Auth Token (solo source maps, opcional pero recomendado)

### Para qué sirve

Solo al **compilar** el frontend (`npm run build:production`). Sube los `.map` a Sentry para ver código TypeScript legible en los issues. **No** es necesario para que los errores lleguen (eso es el DSN del paso 3).

### Dónde lo conseguís en Sentry

1. [Organization Settings → Auth Tokens](https://smartpyme.sentry.io/settings/auth-tokens/)
2. **Create New Token**
3. Permisos típicos: **Project: Read & Write**, **Release: Admin** (o token de org con `org:read`, `project:releases`, `project:write`)
4. Copiá el token (solo se muestra una vez)

### Dónde lo pegás

**En la máquina o CI que hace el build**, no en el servidor Laravel:

```bash
cd Frontend
cp .env.sentry-build-plugin.example .env.sentry-build-plugin
```

Contenido de `.env.sentry-build-plugin` (este archivo **no** se commitea):

```env
SENTRY_AUTH_TOKEN=sntrys_xxxxxxxxxxxxxxxx
SENTRY_RELEASE=frontend@abc1234
```

`SENTRY_RELEASE` es opcional; si no lo ponés, el webpack plugin puede usar `frontend@<git-sha>`.

### Build

```bash
npm ci
npm run build:production
```

- Con token: al final del build deberías ver upload de source maps; los `.map` se borran del `dist`.
- Sin token: el build funciona; verás un aviso `[sentry] SENTRY_AUTH_TOKEN no definido`.

En CI (GitHub Actions, etc.): guardá `SENTRY_AUTH_TOKEN` como **secret** del pipeline, no en el repo.

---

## Paso 5 — Verificación final

| Check | Cómo |
|-------|------|
| Backend | `php artisan sentry:test` + issue en **smartpyme-backend** |
| Frontend | Error desde la UI en prod (no desde consola DevTools) + issue en **smartpyme-frontend** |
| Source maps | Sentry → **smartpyme-frontend** → Settings → Source Maps → Artifact Bundles (después de un build con token) |

---

## Resumen rápido “qué va dónde”

```
Sentry UI
├── smartpyme-backend → Client Keys → DSN  →  Backend/.env  →  SENTRY_LARAVEL_DSN=
├── smartpyme-frontend → Client Keys → DSN  →  Frontend/environment.prod.ts  →  sentry.dsn
└── Org → Auth Tokens  →  token  →  Frontend/.env.sentry-build-plugin  →  SENTRY_AUTH_TOKEN=  (solo build)
```

---

## Desarrollo local

- **FE:** `sentry.enabled: false` en `environment.ts` → no envía nada.
- **BE:** dejá `SENTRY_LARAVEL_DSN` vacío en `.env` local o no corras `sentry:test` si no querés eventos de dev.
