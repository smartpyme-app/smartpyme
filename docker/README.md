# SmartPyme en Docker

Réplica local del servidor de producción: mismas versiones, mismas extensiones de PHP, mismos límites y la misma forma de correr la cola.

## Qué levanta

| Servicio | Equivale en el servidor | Puerto local |
|---|---|---|
| `app` | PHP-FPM 8.4 (pool `apiunificado`) | — |
| `web` | nginx/Apache que sirve la API | http://localhost:8080 |
| `worker` ×3 | supervisor `smartpyme-unificado-worker` (3 procesos) | — |
| `scheduler` | cron `schedule:run` cada minuto (**apagado por defecto**) | — |
| `mariadb` | MariaDB 10.11 (`smartpyme_prod` y `smartpyme_analitica`) | localhost:3307 |
| `redis` | Redis 6.2 (solo cola, vía predis) | localhost:6380 |
| `frontend` | `app.smartpyme.site`, Angular compilado y servido estático | http://localhost:8081 |
| `frontend-dev` | `ng serve` con recarga en vivo (opcional) | http://localhost:4200 |

### Igual que producción

- **PHP 8.4:** bcmath, ftp, gd, mysqli, pdo_mysql, pcntl, xsl, zip y OPcache, más las que trae la imagen oficial. Son las mismas 45 que da `php -m` en el servidor.
- **Límites de PHP:** `memory_limit 128M`, `upload_max_filesize 2M`, `post_max_size 8M`, `max_execution_time 30`, OPcache 512 MB y 60,000 archivos, zona horaria UTC.
- **PHP-FPM:** `ondemand` con 80 procesos y 1000 peticiones por proceso.
- **MariaDB:** `latin1` en el servidor, el mismo `sql_mode`, hora UTC y nombres de tabla sensibles a mayúsculas.
- **Cola:** `queue:work redis --tries=3 --timeout=3600 --max-jobs=1000 --max-time=3600 --memory=512`, 3 procesos.
- **Frontend:** las mismas reglas de caché del `.htaccess` (archivos con hash, 1 año; `index.html` y service worker, sin caché) y la misma regla de rutas a `index.html`.

### Diferencias a propósito

- **Servidor web:** nginx + PHP-FPM en lugar de nginx + Apache de cPanel. Las reglas son las mismas.
- **Memoria de la base:** el buffer pool es de 1 GB en lugar de 16 GB.
- **Source maps:** no se publican; el servidor hoy sí los publica.
- **Scheduler:** apagado salvo que se encienda a mano.
- **Correo:** `MAIL_MAILER=log` en el ejemplo de `.env`, para no enviar correos reales.

## Requisitos

- Docker Desktop con **al menos 8 GB de memoria** (Settings → Resources). La compilación de Angular usa unos 8 GB; con 4 GB el build del frontend se cae.
- Unos 15 GB libres en disco si se carga una copia de la base.

## Primera vez

```bash
# 1. Variables del backend (nunca claves de producción)
cp Backend/.env.docker.example Backend/.env.docker

# 2. Base de datos y Redis
docker compose up -d mariadb redis

# 3. Dependencias PHP y clave de la aplicación
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate --show
#    pegar el valor en APP_KEY= de Backend/.env.docker

# 4. Todo lo demás
docker compose up -d --build
```

## Datos

Las migraciones **todavía no arman la base desde cero**: se detienen en `2025_01_10_170127_create_suscripciones_table` porque la tabla ya la creó una migración anterior. Se corrige en el plan de migraciones.

Mientras tanto, la base se carga desde un respaldo:

1. Copiar `smartpyme_prod.sql.gz` y, si aplica, `smartpyme_analitica.sql.gz` en `docker/mariadb/dumps/`. Esa carpeta nunca se sube a git.
2. Borrar el volumen y volver a levantar: `docker compose down -v && docker compose up -d`. El script `docker/mariadb/init/00-databases.sh` carga los respaldos la primera vez.

> Usar la **base de test liviana** (empresas de prueba, sin datos sensibles) cuando esté lista. No cargar copias de producción en laptops.

## Uso diario

```bash
docker compose up -d                          # levantar
docker compose logs -f app worker             # ver logs
docker compose exec app php artisan tinker    # artisan
docker compose exec app php artisan test      # pruebas
docker compose restart worker                 # tomar código nuevo en la cola (= queue:restart)
docker compose --profile dev-fe up -d         # frontend con recarga en vivo en :4200
docker compose --profile scheduler up -d      # tareas programadas (solo con datos de prueba)
docker compose down                           # apagar (los datos quedan)
docker compose down -v                        # apagar y BORRAR la base local
```

## Imágenes para CI/CD

Las mismas definiciones generan las imágenes de producción:

```bash
# Backend: código y vendor dentro, sin dependencias de desarrollo, autoload optimizado
docker build -f docker/php/Dockerfile --build-context docker=docker --target prod -t smartpyme/backend:prod Backend

# Frontend: API de producción, sin source maps
docker build -f docker/frontend/Dockerfile --build-context docker=docker --build-arg NG_CONFIGURATION=production -t smartpyme/frontend:prod Frontend
```

En el pipeline de GitHub Actions, el mismo contenedor sirve para correr las pruebas (`php artisan test`, `ng test`) con el mismo PHP que producción.

## Fuera de este repositorio

- La **Go API** de dashboards y el **ETL de analítica** (`sp-analytics`).
- **Lucas** (FastAPI y Scylla), que ya corre en contenedores en el servidor.

Cuando sus repositorios entren al mismo flujo, se agregan como servicios a este `docker-compose.yml`.
